<?php

declare(strict_types=1);

/**
 * CLI: ensure the admin and superadmin root accounts exist.
 *
 * Port of backend/cli/admin.ts, simplified to drop the .default_accounts.json
 * config file. Usernames are fixed (admin / superadmin); passwords default to
 * "change-me-123" and can be overridden with the DEFAULT_ADMIN_PASSWORD and
 * DEFAULT_SUPERADMIN_PASSWORD environment variables.
 *
 * Accounts created (or still using the default password) are flagged with
 * must_change_password = 1 so they are forced to pick a new password on their
 * first login.
 *
 * Usage (from repo root):  php backend/cli/admin.php
 */

use Cenusis\Db\Db;
use function Cenusis\Helpers\normalize_arabic;

$backendDir = dirname(__DIR__);

if (file_exists($backendDir . '/vendor/autoload.php')) {
    require $backendDir . '/vendor/autoload.php';
} else {
    require_once $backendDir . '/src/Db/Db.php';
    require_once $backendDir . '/src/helpers/helpers.php';
}

/**
 * Insert the account if it does not exist yet, and ensure accounts that still
 * use the default password are forced to change it on next login.
 *
 * @return array{id: int} created/existing account row
 */
function ensureRootAccount(string $username, string $password, string $role): array
{
    $normalized_username = normalize_arabic($username);

    $existing = Db::first(
        'SELECT id, password_hash FROM loggedin_users WHERE normalized_username = ?',
        [$normalized_username]
    );

    if ($existing !== null) {
        $id = (int) $existing['id'];

        // If the stored hash still verifies against the default password, the
        // account has not been changed yet -> force a password change.
        if (password_verify($password, (string) $existing['password_hash'])) {
            Db::execute(
                'UPDATE loggedin_users SET must_change_password = 1 WHERE id = ?',
                [$id]
            );
            echo sprintf(
                " [SKIP]  %s user already exists (id:%s) but still uses the default password; password change required\n",
                strtoupper($role),
                $id
            );
        } else {
            echo sprintf(" [SKIP]  %s user already exists (id:%s)\n", strtoupper($role), $id);
        }

        return ['id' => $id];
    }

    $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    Db::execute(
        'INSERT INTO loggedin_users (username, normalized_username, role, password_hash, must_change_password)
         VALUES (?, ?, ?, ?, 1)',
        [$username, $normalized_username, $role, $passwordHash]
    );

    $id = (int) Db::pdo()->lastInsertId();
    return ['id' => $id];
}

$accounts = [
    ['admin', 'admin', getenv('DEFAULT_ADMIN_PASSWORD') ?: 'change-me-123'],
    ['superadmin', 'superadmin', getenv('DEFAULT_SUPERADMIN_PASSWORD') ?: 'change-me-123'],
];

foreach ($accounts as [$username, $role, $password]) {
    if (!is_string($password) || strlen($password) < 8) {
        echo sprintf(
            "Error: %s password (username '%s') must be at least 8 characters long\n",
            strtoupper($role),
            $username
        );
        exit(1);
    }
}

foreach ($accounts as [$username, $role, $password]) {
    $result = ensureRootAccount($username, $password, $role);
    echo sprintf(" [DONE]  %s user is ready (id:%d)\n", strtoupper($role), $result['id']);
}
