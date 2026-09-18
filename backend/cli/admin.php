<?php

declare(strict_types=1);

/**
 * CLI: ensure the admin and superadmin root accounts exist.
 *
 * Port of backend/cli/admin.ts.
 *
 * Usage (from repo root):  php backend/cli/admin.php
 *
 * Reads .default_accounts.json from the repo root, expected shape:
 *   {
 *     "admin":      { "username": "...", "password": "..." },
 *     "superadmin": { "username": "...", "password": "..." }
 *   }
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
 * The TS version resolves the config as cwd/../.default_accounts.json (i.e. the
 * repo root when cwd is backend/). Resolve against the repo root, falling back
 * to the cwd-relative path to keep the same behaviour.
 */
function load_config(): array
{
    $candidates = [
        dirname(__DIR__, 2) . '/.default_accounts.json',
        getcwd() . '/../.default_accounts.json',
    ];

    foreach ($candidates as $configPath) {
        if (is_file($configPath) && is_readable($configPath)) {
            $raw = file_get_contents($configPath);
            if ($raw !== false) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }
    }

    return [];
}

/**
 * Insert the account if it does not exist yet (like ensureRootAccount in admin.ts).
 *
 * @return array{id: int} created/existing account row
 */
function ensureRootAccount(string $username, string $password, string $role): array
{
    $normalized_username = normalize_arabic($username);

    $existing = Db::first(
        'SELECT id FROM loggedin_users WHERE normalized_username = ?',
        [$normalized_username]
    );
    if ($existing !== null) {
        echo sprintf(" [SKIP]  %s user already exists (id:%s)\n", strtoupper($role), $existing['id']);
        return ['id' => (int) $existing['id']];
    }

    $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    Db::execute(
        'INSERT INTO loggedin_users (username, normalized_username, role, password_hash)
         VALUES (?, ?, ?, ?)',
        [$username, $normalized_username, $role, $passwordHash]
    );

    $id = (int) Db::pdo()->lastInsertId();
    return ['id' => $id];
}

$config = load_config();

if (
    empty($config['admin']['username']) || empty($config['admin']['password'])
    || empty($config['superadmin']['username']) || empty($config['superadmin']['password'])
) {
    echo "Error: Invalid configuration file\n";
    exit(1);
}

$uid_admin = ensureRootAccount(
    $config['admin']['username'],
    $config['admin']['password'],
    'admin'
);

$uid2_superadmin = ensureRootAccount(
    $config['superadmin']['username'],
    $config['superadmin']['password'],
    'superadmin'
);

echo sprintf(" [DONE]  ADMIN user is ready (id:%d)\n", $uid_admin['id']);
echo sprintf(" [DONE]  SUPERADMIN user is ready (id:%d)\n", $uid2_superadmin['id']);
