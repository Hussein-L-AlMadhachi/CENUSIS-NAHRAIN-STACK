<?php

declare(strict_types=1);

namespace Cenusis\Auth;

use Cenusis\Db\Db;
use Cenusis\Rpc\Metadata;
use Cenusis\Rpc\Response;
use function Cenusis\Helpers\normalize_arabic;
use RuntimeException;

/**
 * Port of backend/src/auth.ts.
 */
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
     */
    public static function generateAuthValidatorForRoles(string $role): callable
    {
        return static function (array $req) use ($role): array {
            try {
                $cookies = $req['cookies'] ?? null;
                if (!is_array($cookies)) {
                    return ['success' => false];
                }

                $token = (string)($cookies[self::COOKIE_NAME] ?? '');
                $decoded = Jwt::decode($token, Jwt::secret());

                if ($decoded === null) {
                    return ['success' => false];
                }

                if (!isset($decoded['user_id'], $decoded['role'])
                    || !is_numeric($decoded['user_id'])
                    || !is_string($decoded['role'])
                ) {
                    return ['success' => false];
                }

                if ($decoded['role'] !== $role) {
                    return ['success' => false];
                }

                return [
                    'success' => true,
                    'metadata' => [
                        'auth' => [
                            'user_id' => (int)$decoded['user_id'],
                            'role' => $decoded['role'],
                        ],
                    ],
                ];
            } catch (\Throwable) {
                return ['success' => false];
            }
        };
    }

    /**
     * Port of login(metadata, username, password).
     * Returns {role, user_id} and sets the auth-token cookie.
     *
     * @return array{role: string, user_id: int}
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

        try {
            $token = Jwt::encode(
                ['role' => $user_role, 'user_id' => $user_id],
                Jwt::secret(),
                self::MAX_AGE_SECONDS,
                $user_role . '-auth-service',
                $user_role
            );
        } catch (\Throwable) {
            throw new RuntimeException('Error creating token');
        }

        $metadata->res->setCookie(self::COOKIE_NAME, $token, [
            'httpOnly' => true,
            'secure' => getenv('NODE_ENV') === 'production',
            'sameSite' => 'lax',
            'maxAge' => self::MAX_AGE_SECONDS, // 1 day
        ]);

        return [
            'role' => $user_role,
            'user_id' => $user_id,
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
     * Port of pg-norm fetchAfterAuth: look up by normalized_username and
     * verify the password (timing-safe via a dummy compare when no user).
     *
     * @return array<string, mixed>|null row with role/id (password_hash removed)
     */
    private static function fetchAfterAuth(string $normalizedUsername, string $plainTextPassword): ?array
    {
        $user = Db::first(
            'SELECT password_hash, role, id FROM loggedin_users WHERE normalized_username = ?',
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
