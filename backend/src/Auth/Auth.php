<?php

declare(strict_types=1);

namespace Cenusis\Auth;

use Cenusis\Db\Db;
use Cenusis\Rpc\Metadata;
use Cenusis\Rpc\Response;
use RuntimeException;
use function Cenusis\Helpers\normalize_arabic;

final class Auth
{
    public const COOKIE_NAME = 'auth-token';
    public const ROLE_COOKIE_NAME = 'auth-role';
    public const MAX_AGE_SECONDS = 60 * 60 * 24; // 1 day

    /** Same dummy bcrypt hash pg-norm uses to keep timing consistent when no user is found. */
    private const DUMMY_PASSWORD_HASH = '$2a$12$4jMZgsZF8HpkBKETdDSKDOIuFwkwYTppUbap/RbTyRCpFuHa2UoCe';

    /**
     * Port of generate_auth_validtor_for_roles(role).
     * Returns a validator closure: fn(array $req): {success: bool, metadata?: array}.
     *
     * Rejects (returns success:false) when the user must change their password.
     */
    public static function generateAuthValidatorForRoles(string $role): callable
    {
        return static function (array $req) use ($role): array {
            $claims = self::decodeAuthClaims($req);

            if ($claims === null || $claims['role'] !== $role) {
                return ['success' => false];
            }

            if ($claims['must_change_password']) {
                return ['success' => false];
            }

            return [
                'success' => true,
                'metadata' => [
                    'auth' => [
                        'user_id' => $claims['user_id'],
                        'role' => $claims['role'],
                    ],
                ],
            ];
        };
    }

    /**
     * Validator for the change-password endpoint: accepts any authenticated
     * role (admin/superadmin/teacher) regardless of must_change_password, so a
     * user forced to change their password can actually do so.
     */
    public static function generateAuthValidatorForAnyRole(): callable
    {
        return static function (array $req): array {
            $claims = self::decodeAuthClaims($req);

            if ($claims === null) {
                return ['success' => false];
            }

            if (!in_array($claims['role'], ['admin', 'superadmin', 'teacher'], true)) {
                return ['success' => false];
            }

            return [
                'success' => true,
                'metadata' => [
                    'auth' => [
                        'user_id' => $claims['user_id'],
                        'role' => $claims['role'],
                    ],
                ],
            ];
        };
    }

    /**
     * Port of login(metadata, username, password).
     * Returns {role, user_id, must_change_password} and sets the auth-token cookie.
     *
     * @return array{role: string, user_id: int, must_change_password: bool}
     */
    public static function login(Metadata $metadata, mixed $username, mixed $password): array
    {
        if (!is_string($password) || !is_string($username)) {
            throw new RuntimeException('RPC expects a username:string , password:string as input');
        }

        $user = self::fetchAfterAuth(normalize_arabic($username), $password);

        if ($user === null) {
            throw new RuntimeException('Unauthorized');
        }

        $user_id = (int)$user['id'];
        $user_role = (string)$user['role'];
        $must_change_password = (bool)($user['must_change_password'] ?? false);

        try {
            self::issueToken($metadata->res, $user_role, $user_id, $must_change_password);
        } catch (\Throwable) {
            throw new RuntimeException('Error creating token');
        }

        return [
            'role' => $user_role,
            'user_id' => $user_id,
            'must_change_password' => $must_change_password,
        ];
    }

    /**
     * Change the currently-authenticated user's password, clear the
     * must_change_password flag, and re-issue the auth token without it.
     *
     * @return array{role: string, user_id: int}
     */
    public static function changeSelfPassword(Metadata $metadata, string $newPassword): array
    {
        $uid = $metadata->auth['user_id'] ?? null;

        if (!is_int($uid)) {
            throw new RuntimeException('You need to be logged in to change your password');
        }

        if ($newPassword === '' || strlen($newPassword) < 8) {
            throw new RuntimeException('Password must be at least 8 characters long');
        }

        $user = Db::first('SELECT role FROM loggedin_users WHERE id = ?', [$uid]);

        if ($user === null) {
            throw new RuntimeException('no user found');
        }

        $role = (string)$user['role'];
        $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

        Db::execute(
            'UPDATE loggedin_users SET password_hash = ?, must_change_password = 0 WHERE id = ?',
            [$passwordHash, $uid]
        );

        self::issueToken($metadata->res, $role, $uid, false);

        return [
            'role' => $role,
            'user_id' => $uid,
        ];
    }

    /**
     * Port of logout(metadata): clears both cookies.
     */
    public static function logout(Metadata $metadata): null
    {
        $metadata->res->setCookie(self::COOKIE_NAME, '');
        $metadata->res->setCookie(self::ROLE_COOKIE_NAME, '');
        return null;
    }

    /**
     * Port of getAccountInfo(metadata).
     *
     * @return array{id: int, username: string, role: string}
     */
    public static function getAccountInfo(Metadata $metadata): array
    {
        $uid = $metadata->auth['user_id'] ?? null;

        if (!is_int($uid)) {
            throw new RuntimeException('Unexpected error: user_id cannot be anythin but a number');
        }

        $user = Db::first(
            'SELECT id, username, role FROM loggedin_users WHERE id = ?',
            [$uid]
        );

        if ($user === null) {
            throw new RuntimeException('no user found');
        }

        return [
            'id' => (int)$user['id'],
            'username' => (string)$user['username'],
            'role' => (string)$user['role'],
        ];
    }

    /**
     * Port of isValidAdminNoRPC: returns the auth claims or null.
     *
     * @return array<string, mixed>|null
     */
    public static function isValidAdminNoRPC(array $req): ?array
    {
        return self::noRpcAuth('admin', $req);
    }

    /** Port of isValidSuperadminNoRPC. */
    public static function isValidSuperadminNoRPC(array $req): ?array
    {
        return self::noRpcAuth('superadmin', $req);
    }

    /** Port of isValidTeacherNoRPC. */
    public static function isValidTeacherNoRPC(array $req): ?array
    {
        return self::noRpcAuth('teacher', $req);
    }

    /**
     * Decode and validate the auth-token cookie into claims, or return null.
     *
     * @return array{user_id: int, role: string, must_change_password: bool}|null
     */
    private static function decodeAuthClaims(array $req): ?array
    {
        try {
            $cookies = $req['cookies'] ?? null;
            if (!is_array($cookies)) {
                return null;
            }

            $token = (string)($cookies[self::COOKIE_NAME] ?? '');
            $decoded = Jwt::decode($token, Jwt::secret());

            if ($decoded === null) {
                return null;
            }

            if (!isset($decoded['user_id'], $decoded['role'])
                || !is_numeric($decoded['user_id'])
                || !is_string($decoded['role'])
            ) {
                return null;
            }

            return [
                'user_id' => (int)$decoded['user_id'],
                'role' => $decoded['role'],
                'must_change_password' => (bool)($decoded['must_change_password'] ?? false),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Issue (or refresh) the auth-token cookie for a user.
     */
    private static function issueToken(Response $res, string $role, int $userId, bool $mustChange): void
    {
        $token = Jwt::encode(
            ['role' => $role, 'user_id' => $userId, 'must_change_password' => $mustChange],
            Jwt::secret(),
            self::MAX_AGE_SECONDS,
            $role . '-auth-service',
            $role
        );

        $res->setCookie(self::COOKIE_NAME, $token, [
            'httpOnly' => true,
            'secure' => getenv('NODE_ENV') === 'production',
            'sameSite' => 'lax',
            'maxAge' => self::MAX_AGE_SECONDS, // 1 day
        ]);
    }

    /**
     * Port of pg-norm fetchAfterAuth: look up by normalized_username and
     * verify the password (timing-safe via a dummy compare when no user).
     *
     * @return array<string, mixed>|null row with role/id/must_change_password (password_hash removed)
     */
    private static function fetchAfterAuth(string $normalizedUsername, string $plainTextPassword): ?array
    {
        $user = Db::first(
            'SELECT password_hash, role, id, must_change_password FROM loggedin_users WHERE normalized_username = ?',
            [$normalizedUsername]
        );

        if ($user === null || empty($user['password_hash'])) {
            password_verify('dummy_password', self::DUMMY_PASSWORD_HASH); // constant-time-ish no-op
            return null;
        }

        if (!password_verify($plainTextPassword, (string)$user['password_hash'])) {
            return null;
        }

        unset($user['password_hash']);
        return $user;
    }

    private static function noRpcAuth(string $role, array $req): ?array
    {
        $validator = self::generateAuthValidatorForRoles($role);
        $validation = $validator($req);

        if (($validation['success'] ?? false) === false) {
            return null;
        }

        return $validation['metadata']['auth'] ?? null;
    }
}
