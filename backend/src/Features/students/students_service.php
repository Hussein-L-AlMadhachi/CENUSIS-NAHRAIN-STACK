<?php

declare(strict_types=1);

/**
 * Port of backend/src/Features/students/students.sql.ts (StudentsTable)
 * and students.service.ts.
 *
 * Table helpers become plain functions with raw SQL (Cenusis\Db\Db); the RPC
 * handlers keep the exact TS export names and are registered by
 * studentsLoader() in students_loader.php.
 */

use Cenusis\Db\Db;
use Cenusis\Rpc\Metadata;
use function Cenusis\Helpers\normalize_arabic;
use function Cenusis\Helpers\validate_params;
use function Cenusis\Helpers\loose_validate_params;

/** pg-norm visibles for students (SELECT list). */
const STUDENTS_VISIBLES = ['id', 'student_name', 'student_normalized_name', 'degree', 'class'];

/**
 * MySQL schema adds sex / years_retaken / years_failed (used by importData
 * and updateStudent), allowed as write fillables.
 */
const STUDENTS_FILLABLES = [
    'id', 'student_name', 'student_normalized_name', 'degree', 'class',
    'sex', 'years_retaken', 'years_failed',
];

// ---------------------------------------------------------------------------
// Table helpers (students.sql.ts)
// ---------------------------------------------------------------------------

/**
 * Port of PG_Table.insert (RETURNING id -> lastInsertId).
 *
 * @param array<string, mixed> $data
 * @return array<int, array<string, mixed>>
 */
function students_insert(array $data): array
{
    foreach (array_keys($data) as $key) {
        if (!in_array($key, STUDENTS_FILLABLES, true)) {
            throw new Exception('inserted rows need to have all columns in visibles');
        }
    }

    $columns = array_keys($data);
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    ['lastInsertId' => $lastInsertId] = Db::execute(
        'INSERT INTO students (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')',
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
function students_fetch(int $row_id): array
{
    return Db::query(
        'SELECT ' . implode(', ', STUDENTS_VISIBLES) . ' FROM students WHERE id = ?',
        [$row_id]
    );
}

/**
 * Port of StudentsTable.listAll (ORDER BY student_name ASC).
 *
 * @return array<int, array<string, mixed>>
 */
function students_list_all(): array
{
    return Db::query(
        'SELECT ' . implode(', ', STUDENTS_VISIBLES) . ' FROM students ORDER BY student_name ASC'
    );
}

/**
 * Port of PG_Table.update (RETURNING id -> affected id).
 *
 * @param array<string, mixed> $data
 * @return array<int, array<string, mixed>>
 */
function students_update(int $row_id, array $data): array
{
    foreach (array_keys($data) as $key) {
        if (!in_array($key, STUDENTS_FILLABLES, true)) {
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

    Db::execute('UPDATE students SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);
    return [['id' => $row_id]];
}

/**
 * Port of PG_Table.delete (hard delete).
 */
function students_delete(int $row_id): array
{
    Db::execute('DELETE FROM students WHERE id = ?', [$row_id]);
    return [];
}

/**
 * Port of StudentsTable.autocomplete (pg_trgm replaced by LIKE %..%).
 *
 * @return array<int, array<string, mixed>>
 */
function students_autocomplete(string $searched_name): array
{
    return Db::query(
        'SELECT student_name, student_normalized_name
         FROM students
         WHERE student_normalized_name LIKE ?
         ORDER BY student_name ASC LIMIT 10',
        ['%' . $searched_name . '%']
    );
}

/**
 * Port of StudentsTable.importData: batched inserts (100 per batch) with
 * ON DUPLICATE KEY UPDATE on the normalized name (UNIQUE key).
 *
 * @param array<int, array<string, mixed>> $data
 */
function students_import_data(array $data): void
{
    $validatedData = [];
    foreach ($data as $item) {
        $validatedData[] = [
            'student_name' => (string)($item['student_name'] ?? ''),
            'student_normalized_name' => normalize_arabic($item['student_name'] ?? null),
            'degree' => (string)($item['degree'] ?? ''),
            'class' => (int)($item['class'] ?? 0) ?: 1,
            'sex' => (string)($item['sex'] ?? ''),
            'years_retaken' => (int)($item['years_retaken'] ?? 0),
            'years_failed' => (int)($item['years_failed'] ?? 0),
        ];
    }

    // Batch insert for better performance with large datasets
    $batchSize = 100;

    $batches = array_chunk($validatedData, $batchSize);
    foreach ($batches as $batch) {
        $placeholders = [];
        $params = [];
        foreach ($batch as $row) {
            $placeholders[] = '(' . implode(', ', array_fill(0, count($row), '?')) . ')';
            foreach ($row as $value) {
                $params[] = $value;
            }
        }

        Db::execute(
            'INSERT INTO students (student_name, student_normalized_name, degree, class, sex, years_retaken, years_failed)
             VALUES ' . implode(', ', $placeholders) . '
             ON DUPLICATE KEY UPDATE
                student_name = VALUES(student_name),
                student_normalized_name = VALUES(student_normalized_name),
                degree = VALUES(degree),
                class = VALUES(class),
                sex = VALUES(sex),
                years_retaken = VALUES(years_retaken),
                years_failed = VALUES(years_failed)',
            $params
        );
    }
}

/**
 * Port of StudentsTable.findByName (single row or null).
 *
 * @return array<string, mixed>|null
 */
function students_find_by_name(string $student_normalized_name): ?array
{
    return Db::first(
        'SELECT ' . implode(', ', STUDENTS_VISIBLES)
            . ' FROM students WHERE student_normalized_name = ?',
        [$student_normalized_name]
    );
}

/**
 * Port of StudentsTable.filterStudentsByClassDegree.
 *
 * @return array<int, array<string, mixed>>
 */
function students_filter_by_class_degree(string $degree, int $student_class): array
{
    return Db::query(
        'SELECT ' . implode(', ', STUDENTS_VISIBLES)
            . ' FROM students WHERE degree = ? AND class = ?',
        [$degree, $student_class]
    );
}

/**
 * Port of StudentsTable.filterStudentsByDegree.
 *
 * @return array<int, array<string, mixed>>
 */
function students_filter_by_degree(string $degree): array
{
    return Db::query(
        'SELECT ' . implode(', ', STUDENTS_VISIBLES) . ' FROM students WHERE degree = ?',
        [$degree]
    );
}

/**
 * Port of StudentsTable.InsertOrUpdate (ON DUPLICATE KEY UPDATE
 * student_name/degree/class on the normalized name UNIQUE key).
 */
function students_insert_or_update(string $name, string $degree, int $class_number): array
{
    Db::execute(
        'INSERT INTO students (student_name, student_normalized_name, degree, class)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            student_name = VALUES(student_name),
            degree = VALUES(degree),
            class = VALUES(class)',
        [$name, normalize_arabic($name), $degree, $class_number]
    );
    return [];
}

// ---------------------------------------------------------------------------
// RPC handlers (students.service.ts)
// ---------------------------------------------------------------------------

function newStudent(Metadata $metadata, array $data)
{
    validate_params($data, ['student_name', 'degree', 'class']);

    if ($data['degree'] !== 'بكلوريوس' && $data['degree'] !== 'ماجستير' && $data['degree'] !== 'دكتوراه') {
        throw new Exception("degree must be 'بكلوريوس' or 'ماجستير' or 'دكتوراه'");
    }

    $data['student_normalized_name'] = normalize_arabic($data['student_name']);
    $user = students_insert($data)[0] ?? null;

    if ($user === null) {
        throw new Exception('User is already in the database');
    }

    return (int)$user['id'];
}

function updateStudent(Metadata $metadata, int $uid, array $data)
{
    loose_validate_params($data, ['student_name', 'degree', 'class', 'sex']);

    if ($data['sex'] !== 'ذكر' && $data['sex'] !== 'انثى') {
        throw new Exception("sex must be 'ذكر' or 'انثى'");
    }

    $data['student_normalized_name'] = normalize_arabic($data['student_name']);

    if (!is_int($uid)) {
        throw new Exception('Unexpected error: user_id cannot be anythin but a number');
    }

    students_update($uid, $data);
    return $uid;
}

function deleteStudent(Metadata $metadata, int $id): void
{
    students_delete($id);
}

function fetchStudentInfo(Metadata $metadata, int $id)
{
    $rows = students_fetch($id);
    $result = $rows[0] ?? null;

    if ($result === null) {
        throw new Exception('no user found');
    }

    return $result;
}

function fetchStudents(Metadata $metadata): array
{
    $result = students_list_all();

    if (!is_array($result)) {
        throw new Exception('no user found');
    }

    return $result;
}

function filterStudentsByClassDegree(Metadata $metadata, string $degree, int $student_class): array
{
    $result = students_filter_by_class_degree($degree, $student_class);

    if (!is_array($result)) {
        throw new Exception('no user found');
    }

    return $result;
}

function filterStudentsByDegree(Metadata $metadata, string $degree): array
{
    $result = students_filter_by_degree($degree);

    if (!is_array($result)) {
        throw new Exception('no user found');
    }

    return $result;
}

/**
 * @return array<int, string>
 */
function autocompleteStudent(Metadata $metadata, string $name): array
{
    $result = students_autocomplete($name);

    if (!is_array($result)) {
        throw new Exception('no user found');
    }

    $flattened_result = [];
    foreach ($result as $user) {
        $flattened_result[] = $user['student_name'];
    }

    return $flattened_result;
}

function findStudentByName(Metadata $metadata, string $name)
{
    $result = students_find_by_name(normalize_arabic($name));

    if ($result === null) {
        throw new Exception('no user found');
    }

    return $result;
}
