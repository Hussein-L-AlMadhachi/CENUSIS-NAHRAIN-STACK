<?php

declare(strict_types=1);

/**
 * Port of backend/src/Features/loggedin_users/loggedin_users.sql.ts (PG_AuthTable).
 *
 * Plain-function port with raw SQL through the PDO helper (Cenusis\Db\Db).
 * No RPC handlers here, this table is only used by other feature services
 * and by the Auth RPCs (backend/src/Auth/Auth.php).
 */

use Cenusis\Db\Db;

/** pg-norm visibles/fillables for loggedin_users. */
const LOGGEDIN_USERS_FILLABLES = ['id', 'username', 'normalized_username', 'role'];

/** pg-norm PG_AuthTable password column. */
const LOGGEDIN_USERS_PASSWORD_FIELD = 'password_hash';

/** Same dummy bcrypt hash pg-norm uses to keep timing consistent when no user is found. */
const LOGGEDIN_USERS_DUMMY_PASSWORD_HASH = '$2a$12$4jMZgsZF8HpkBKETdDSKDOIuFwkwYTppUbap/RbTyRCpFuHa2UoCe';

/**
 * Port of PG_AuthTable.hashPassword (bcrypt, cost 12, min length 8).
 */
function loggedin_users_hash_password(string $password): string
{
    if ($password === '' || strlen($password) < 8) {
        throw new Exception('Password must be at least 8 characters long');
    }

    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Port of PG_AuthTable.insert: hashes 'password' when present, then
 * validates every key against the fillables (+ password_hash).
 *
 * @param array<string, mixed> $data
 * @return array<int, array<string, mixed>> row list containing the new id
 */
function loggedin_users_insert(array $data): array
{
    // Hash password if present, remove the plain text one
    if (isset($data['password'])) {
        $data[LOGGEDIN_USERS_PASSWORD_FIELD] = loggedin_users_hash_password((string)$data['password']);
        unset($data['password']);
    }

    // Validate keys against fillables
    foreach (array_keys($data) as $key) {
        if (!in_array($key, LOGGEDIN_USERS_FILLABLES, true) && $key !== LOGGEDIN_USERS_PASSWORD_FIELD) {
            throw new Exception($key . ' has to be a fillable');
        }
    }

    $columns = array_keys($data);
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    ['lastInsertId' => $lastInsertId] = Db::execute(
        'INSERT INTO loggedin_users (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')',
        array_values($data)
    );

    $id = array_key_exists('id', $data) ? (int)$data['id'] : (int)$lastInsertId;
    return [['id' => $id]];
}

/**
 * Port of PG_Table.fetch.
 *
 * @return array<int, array<string, mixed>>
 */
function loggedin_users_fetch(int $id): array
{
    return Db::query(
        'SELECT id, username, normalized_username, role FROM loggedin_users WHERE id = ?',
        [$id]
    );
}

/**
 * Port of PG_AuthTable.findByUserName.
 *
 * @return array<string, mixed>|null
 */
function loggedin_users_find_by_user_name(string $normalized_username): ?array
{
    return Db::first(
        'SELECT id, username, normalized_username, role FROM loggedin_users WHERE normalized_username = ?',
        [$normalized_username]
    );
}

/**
 * Port of PG_Table.update. Validates keys, returns the affected id rows.
 *
 * @param array<string, mixed> $data
 * @return array<int, array<string, mixed>>
 */
function loggedin_users_update(int $id, array $data): array
{
    foreach (array_keys($data) as $key) {
        if (!in_array($key, LOGGEDIN_USERS_FILLABLES, true) && $key !== LOGGEDIN_USERS_PASSWORD_FIELD) {
            throw new Exception($key . ' has to be a fillable');
        }
    }

    $sets = [];
    $params = [];
    foreach ($data as $key => $value) {
        $sets[] = $key . ' = ?';
        $params[] = $value;
    }
    $params[] = $id;

    Db::execute('UPDATE loggedin_users SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);
    return [['id' => $id]];
}

/**
 * Port of PG_Table.delete (hard delete).
 */
function loggedin_users_delete(int $id): array
{
    Db::execute('DELETE FROM loggedin_users WHERE id = ?', [$id]);
    return [];
}

/**
 * Port of PG_AuthTable.updatePassword: secure hashing then UPDATE.
 *
 * @return array<int, array<string, mixed>>
 */
function loggedin_users_update_password(int $userId, string $newPassword): array
{
    $passwordHash = loggedin_users_hash_password($newPassword);

    Db::execute(
        'UPDATE loggedin_users SET password_hash = ? WHERE id = ?',
        [$passwordHash, $userId]
    );

    return [['id' => $userId]];
}

/**
 * Port of PG_AuthTable.fetchAfterAuth: look up by normalized_username,
 * timing-safe dummy verify when the user/hash is missing, password_verify,
 * and strip password_hash from the returned row.
 *
 * @param array<int, string> $columns visible columns to select
 * @return array<string, mixed>|null
 */
function loggedin_users_fetch_after_auth(string $normalized_username, string $password, array $columns): ?array
{
    foreach ($columns as $column) {
        if (!in_array($column, LOGGEDIN_USERS_FILLABLES, true)) {
            throw new Exception('access to non-viisble non-authorized columns is restricted');
        }
    }

    $select = implode(', ', array_merge([LOGGEDIN_USERS_PASSWORD_FIELD], $columns));
    $user = Db::first(
        'SELECT ' . $select . ' FROM loggedin_users WHERE normalized_username = ?',
        [$normalized_username]
    );

    if ($user === null || empty($user[LOGGEDIN_USERS_PASSWORD_FIELD])) {
        // when the user doesn't exist act like we're trying to log them in so
        // they can't use timing attacks
        password_verify('dummy_password', LOGGEDIN_USERS_DUMMY_PASSWORD_HASH);
        return null;
    }

    if (!password_verify($password, (string)$user[LOGGEDIN_USERS_PASSWORD_FIELD])) {
        return null;
    }

    unset($user[LOGGEDIN_USERS_PASSWORD_FIELD]);
    return $user;
}
