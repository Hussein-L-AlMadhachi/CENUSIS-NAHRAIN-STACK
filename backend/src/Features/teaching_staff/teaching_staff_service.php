<?php

declare(strict_types=1);

/**
 * Port of backend/src/Features/teaching_staff/teaching_staff.sql.ts (TeachingStaff)
 * and teaching_staff.service.ts.
 *
 * Table helpers become plain functions with raw SQL (Cenusis\Db\Db), the RPC
 * handlers keep the exact TS export names (they are the RPC method names the
 * frontend calls) and are registered by teachingStaffLoader() in
 * teaching_staff_loader.php.
 */

use Cenusis\Db\Db;
use Cenusis\Rpc\Metadata;
use function Cenusis\Helpers\normalize_arabic;
use function Cenusis\Helpers\validate_params;
use function Cenusis\Helpers\loose_validate_params;

require_once __DIR__ . '/../loggedin_users/loggedin_users_service.php';

/** pg-norm visibles for teaching_staff. */
const TEACHING_STAFF_VISIBLES = [
    'id', 'teacher_name', 'teacher_normalized_name',
    'availability_bitmap', 'hours_available', 'login_credentials', 'registered_at',
];

// ---------------------------------------------------------------------------
// Table helpers (teaching_staff.sql.ts)
// ---------------------------------------------------------------------------

/**
 * Port of PG_Table.insert (RETURNING id -> lastInsertId).
 *
 * @param array<string, mixed> $data
 * @return array<int, array<string, mixed>>
 */
function teaching_staff_insert(array $data): array
{
    foreach (array_keys($data) as $key) {
        if (!in_array($key, TEACHING_STAFF_VISIBLES, true)) {
            throw new Exception('inserted rows need to have all columns in visibles');
        }
    }

    $columns = array_keys($data);
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    ['lastInsertId' => $lastInsertId] = Db::execute(
        'INSERT INTO teaching_staff (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')',
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
function teaching_staff_fetch(int $row_id): array
{
    return Db::query(
        'SELECT ' . implode(', ', TEACHING_STAFF_VISIBLES) . ' FROM teaching_staff WHERE id = ?',
        [$row_id]
    );
}

/**
 * Port of PG_Table.listAll.
 *
 * @return array<int, array<string, mixed>>
 */
function teaching_staff_list_all(): array
{
    return Db::query(
        'SELECT ' . implode(', ', TEACHING_STAFF_VISIBLES) . ' FROM teaching_staff'
    );
}

/**
 * Port of PG_Table.update (RETURNING id -> affected id).
 *
 * @param array<string, mixed> $data
 * @return array<int, array<string, mixed>>
 */
function teaching_staff_update(int $row_id, array $data): array
{
    foreach (array_keys($data) as $key) {
        if (!in_array($key, TEACHING_STAFF_VISIBLES, true)) {
            throw new Exception('inserted rows need to have all columns in visibles');
        }
    }

    $sets = [];
    $params = [];
    foreach ($data as $key => $value) {
        $sets[] = $key . ' = ?';
        $params[] = $value;
    }
    $params[] = $row_id;

    Db::execute('UPDATE teaching_staff SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);
    return [['id' => $row_id]];
}

/**
 * Port of PG_Table.delete (hard delete).
 */
function teaching_staff_delete(int $row_id): array
{
    Db::execute('DELETE FROM teaching_staff WHERE id = ?', [$row_id]);
    return [];
}

/**
 * Port of TeachingStaff.autocomplete (pg_trgm word_similarity is not
 * available on MySQL, LIKE %..% on the normalized name instead).
 *
 * @return array<int, array<string, mixed>>
 */
function teaching_staff_autocomplete(string $searched_name): array
{
    return Db::query(
        'SELECT teacher_name, teacher_normalized_name
         FROM teaching_staff
         WHERE teacher_normalized_name LIKE ?
         ORDER BY teacher_name ASC LIMIT 10',
        ['%' . $searched_name . '%']
    );
}

/**
 * Port of TeachingStaff.findByName (single row or null).
 *
 * @return array<string, mixed>|null
 */
function teaching_staff_find_by_name(string $normalized_name): ?array
{
    return Db::first(
        'SELECT ' . implode(', ', TEACHING_STAFF_VISIBLES)
            . ' FROM teaching_staff WHERE teacher_normalized_name = ?',
        [$normalized_name]
    );
}

/**
 * Port of TeachingStaff.fetchByUserId (single row or null).
 *
 * @return array<string, mixed>|null
 */
function teaching_staff_fetch_by_user_id(int $user_id): ?array
{
    return Db::first(
        'SELECT ' . implode(', ', TEACHING_STAFF_VISIBLES)
            . ' FROM teaching_staff WHERE login_credentials = ?',
        [$user_id]
    );
}

// ---------------------------------------------------------------------------
// RPC handlers (teaching_staff.service.ts)
// ---------------------------------------------------------------------------

function registerTeacher(Metadata $metadata, array $data)
{
    validate_params($data, ['teacher_name', 'password']);

    if (empty($data['password'])) {
        throw new Exception('password field is required');
    }

    if (!empty($data['teacher_name'])) {
        $data['teacher_normalized_name'] = normalize_arabic($data['teacher_name']);
    } else {
        throw new Exception('teacher_name field is required');
    }

    $user_data = [
        'username' => $data['teacher_name'],
        'normalized_username' => $data['teacher_normalized_name'],
        'role' => 'teacher',
        'password' => $data['password'],
    ];

    $user = loggedin_users_insert($user_data)[0];

    $teacher_data = [
        'teacher_name' => $data['teacher_name'],
        'teacher_normalized_name' => $data['teacher_normalized_name'],
        'login_credentials' => (int)$user['id'],
    ];

    try {
        $teacher = teaching_staff_insert($teacher_data)[0] ?? null;

        if ($teacher === null) {
            loggedin_users_delete((int)$user['id']);
        }
    } catch (Exception $e) {
        // NOTE: TS reads user.uid here (which does not exist); we delete by id.
        loggedin_users_delete((int)$user['id']);
        throw $e;
    }

    return (int)$teacher['id'];
}

function registerAdmin(Metadata $metadata, string $name, string $password)
{
    $rows = loggedin_users_insert([
        'username' => $name,
        'normalized_username' => normalize_arabic($name),
        'role' => 'admin',
        'password' => $password,
    ]);

    return (int)$rows[0]['id'];
}

function deleteUser(Metadata $metadata, int $id): void
{
    $rows = teaching_staff_fetch($id);
    $teacher = $rows[0] ?? null;

    if ($teacher === null) {
        throw new Exception('التدريسي غير موجود');
    }

    teaching_staff_delete($id);
    loggedin_users_delete((int)$teacher['login_credentials']);
}

function updateUser(Metadata $metadata, $teacher_id, array $data)
{
    // The frontend (EditableTable onSave) sends {id, teacher_name, password}
    // and passes the id as a string. The old TS code validated for
    // ['name', 'password'] and always threw 'missing key name', so the UI's
    // password reset silently failed (the promise rejection was swallowed).
    // Accept the real frontend contract: teacher_name (+ legacy 'name'),
    // optional password, string id.
    $teacher_id = (int)$teacher_id;

    if (array_key_exists('id', $data)) {
        unset($data['id']);
    }

    foreach (array_keys($data) as $key) {
        if (!in_array($key, ['name', 'teacher_name', 'password'], true)) {
            throw new Exception('invalid request: unexpected key ' . $key);
        }
    }

    $name = $data['teacher_name'] ?? $data['name'] ?? null;

    $teacher = teaching_staff_fetch($teacher_id);

    if (count($teacher) !== 1) {
        throw new Exception('لا يوجد استاذ بهذا الاسم');
    }

    $uid = (int)$teacher[0]['login_credentials'];

    if (!empty($data['password'])) {
        changeTeacherPassword($metadata, $uid, (string)$data['password']);
    }

    if (is_string($name) && trim($name) !== '') {
        $teacher_normalized_name = normalize_arabic($name);

        // Update the correct rows: teaching_staff by teacher_id, and keep the
        // login credentials username in sync (the old TS updated the
        // teaching_staff row by uid instead of teacher_id).
        teaching_staff_update($teacher_id, [
            'teacher_name' => $name,
            'teacher_normalized_name' => $teacher_normalized_name,
        ]);
        loggedin_users_update($uid, [
            'username' => $name,
            'normalized_username' => $teacher_normalized_name,
        ]);
    }

    return $uid;
}

function updateSelf(Metadata $metadata, array $data)
{
    loose_validate_params($data, ['name']);

    $uid = $metadata->auth['user_id'] ?? null;
    if (!is_int($uid)) {
        throw new Exception('Unexpected error: user_id cannot be anythin but a number');
    }

    $data['teacher_normalized_name'] = normalize_arabic($data['teacher_name'] ?? null);

    teaching_staff_update($uid, [
        'teacher_name' => $data['teacher_name'] ?? null,
        'teacher_normalized_name' => $data['teacher_normalized_name'],
    ]);
    loggedin_users_update($uid, [
        'username' => $data['teacher_name'] ?? null,
        'normalized_username' => $data['teacher_normalized_name'],
    ]);

    return $uid;
}

function getProfile(Metadata $metadata)
{
    $uid = $metadata->auth['user_id'] ?? null;

    if (!is_int($uid)) {
        throw new Exception('Unexpected error: user_id cannot be anythin but a number');
    }

    $rows = teaching_staff_fetch($uid);
    $result = $rows[0] ?? null;

    if ($result === null) {
        throw new Exception('no user found');
    }

    return $result;
}

function changeTeacherPassword(Metadata $metadata, int $id, string $new_password)
{
    $uid = loggedin_users_update_password($id, $new_password);

    if (!$uid) {
        throw new Exception("couldn't find user");
    }

    return $uid;
}

/**
 * @return array<int, string>
 */
function fetchTeachers(Metadata $metadata): array
{
    $uid = $metadata->auth['user_id'] ?? null;

    if (!is_int($uid)) {
        throw new Exception('Unexpected error: user_id cannot be anythin but a number');
    }

    $result = teaching_staff_list_all();

    if (!is_array($result)) {
        throw new Exception('no user found');
    }

    return $result;
}

function registerMeAsTeacher(Metadata $metadata, array $data): array
{
    $uid = $metadata->auth['user_id'] ?? null;

    if (!is_int($uid)) {
        throw new Exception('Unexpected error: user_id cannot be anythin but a number');
    }

    // NOTE: TS passes {id, name, normalized_name} whose keys are not in the
    // table visibles, so insert() throws (ported as-is).
    $rows = teaching_staff_insert([
        'id' => $uid,
        'name' => $data['name'] ?? null,
        'normalized_name' => normalize_arabic($data['name'] ?? null),
    ]);

    $result = $rows[0] ?? null;

    if ($result === null) {
        throw new Exception('CRITICAL ERROR: this should be impossible; this could be a massive security vulnerbility. Report this to developer.');
    }

    return $result;
}

/**
 * @return array<int, string>
 */
function autocompleteTeacher(Metadata $metadata, string $name): array
{
    $result = teaching_staff_autocomplete($name);

    if (!is_array($result)) {
        throw new Exception('no user found');
    }

    $list = [];
    foreach ($result as $row) {
        $list[] = $row['teacher_name'];
    }

    return $list;
}
