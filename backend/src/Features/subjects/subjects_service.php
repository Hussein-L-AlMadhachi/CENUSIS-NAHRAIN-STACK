<?php

declare(strict_types=1);

/**
 * Port of backend/src/Features/subjects/subjects.sql.ts (Subjects)
 * and subjects.service.ts.
 *
 * Table helpers become plain functions with raw SQL (Cenusis\Db\Db); the RPC
 * handlers keep the exact TS export names and are registered by
 * subjectsLoader() in subjects_loader.php.
 */

use Cenusis\Db\Db;
use Cenusis\Rpc\Metadata;
use function Cenusis\Helpers\normalize_arabic;
use function Cenusis\Helpers\validate_params;
use function Cenusis\Helpers\loose_validate_params;
use function Cenusis\Helpers\row_cast;

require_once __DIR__ . '/../teaching_staff/teaching_staff_service.php';
require_once __DIR__ . '/../grading_system/grading_systems_service.php';

/** pg-norm visibles for subjects. */
const SUBJECTS_VISIBLES = [
    'id', 'subject_name', 'subject_normalized_name', 'teacher',
    'degree', 'class', 'total_hours', 'hours_weekly',
    'is_attending_required', 'semester', 'grading_system_id',
    'deleted_at', 'lab_teacher', 'max_lab_grade', 'lab_grade_field', 'lab_weekly_hours',
];

/**
 * Cast MySQL native types back to the shapes PostgreSQL returned
 * (is_attending_required / has_lab as real booleans).
 */
function subjects_cast_row(array $row): array
{
    return row_cast($row, ['is_attending_required', 'has_lab']);
}

/** subjects.sql.ts columns2select (backtick-quoted; `class` is reserved in MySQL). */
const SUBJECTS_COLUMNS2SELECT = [
    '`subjects`.`id`', '`subjects`.`subject_name`', '`subjects`.`subject_normalized_name`', '`subjects`.`teacher`',
    '`subjects`.`degree`', '`subjects`.`class`', '`subjects`.`total_hours`', '`subjects`.`hours_weekly`',
    '`subjects`.`is_attending_required`', '`subjects`.`semester`', '`subjects`.`grading_system_id`',
    '`subjects`.`lab_teacher`', '`subjects`.`max_lab_grade`', '`subjects`.`lab_grade_field`', '`subjects`.`lab_weekly_hours`',
    '`main_teacher`.`teacher_name`',
];

/** subjects.service.ts key lists. */
const SUBJECTS_REQUIRED_KEYS = [
    'subject_name', 'degree', 'class', 'hours_weekly', 'teacher_name', 'semester', 'grading_system_name',
];

const SUBJECTS_ALLOWED_KEYS = [
    'subject_name', 'degree', 'class', 'hours_weekly', 'teacher_name', 'semester', 'grading_system_name',
    'has_lab', 'lab_teacher_name', 'max_lab_grade', 'lab_grade_field', 'lab_weekly_hours', 'lab_hours_weekly',
];

// ---------------------------------------------------------------------------
// Table helpers (subjects.sql.ts)
// ---------------------------------------------------------------------------

/**
 * Port of PG_Table.insert (RETURNING id -> lastInsertId).
 *
 * @param array<string, mixed> $data
 * @return array<int, array<string, mixed>>
 */
function subjects_insert(array $data): array
{
    foreach (array_keys($data) as $key) {
        if (!in_array($key, SUBJECTS_VISIBLES, true)) {
            throw new Exception('inserted rows need to have all columns in visibles');
        }
    }

    $columns = array_keys($data);
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    ['lastInsertId' => $lastInsertId] = Db::execute(
        'INSERT INTO subjects (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')',
        array_values($data)
    );

    $id = array_key_exists('id', $data) ? (int)$data['id'] : (int)$lastInsertId;
    return [['id' => $id]];
}

/**
 * Port of PG_Table.fetch (includes deleted_at in visibles).
 *
 * @return array<int, array<string, mixed>>
 */
function subjects_fetch(int $row_id): array
{
    return array_map(
        'subjects_cast_row',
        Db::query(
            'SELECT ' . implode(', ', SUBJECTS_VISIBLES) . ' FROM subjects WHERE id = ?',
            [$row_id]
        )
    );
}

/**
 * Port of PG_Table.update (RETURNING id -> affected id).
 *
 * @param array<string, mixed> $data
 * @return array<int, array<string, mixed>>
 */
function subjects_update(int $row_id, array $data): array
{
    foreach (array_keys($data) as $key) {
        if (!in_array($key, SUBJECTS_VISIBLES, true)) {
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

    Db::execute('UPDATE subjects SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);
    return [['id' => $row_id]];
}

/**
 * Port of Subjects.delete override: SOFT delete (deleted_at).
 */
function subjects_delete(int $subject_id): array
{
    Db::execute('UPDATE subjects SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?', [$subject_id]);
    return [];
}

/**
 * Shared SELECT prefix: columns2select + lab_teacher_name + has_lab +
 * grading_system_name with the main_teacher / lab_teacher / grading_systems
 * JOINs. filterByClassDegreeGradingSystem uses an INNER JOIN on
 * grading_systems (matching the TS query).
 */
function subjects_base_select(bool $inner_join_grading_systems = false): string
{
    $gradingJoin = $inner_join_grading_systems ? 'JOIN' : 'LEFT JOIN';

    return 'SELECT ' . implode(', ', SUBJECTS_COLUMNS2SELECT)
        . ', `lab_teacher`.`teacher_name` AS lab_teacher_name'
        . ', CASE WHEN `subjects`.`lab_teacher` IS NULL THEN FALSE ELSE TRUE END AS has_lab'
        . ', `grading_systems`.`name` AS grading_system_name'
        . ' FROM `subjects`'
        . ' JOIN `teaching_staff` AS main_teacher ON `subjects`.`teacher` = `main_teacher`.`id`'
        . ' LEFT JOIN `teaching_staff` AS `lab_teacher` ON `subjects`.`lab_teacher` = `lab_teacher`.`id`'
        . ' ' . $gradingJoin . ' `grading_systems` ON `subjects`.`grading_system_id` = `grading_systems`.`id`';
}

/**
 * Port of Subjects.filterByYear.
 * NOTE: `year` is not a subjects column in the MySQL schema, ported as-is
 * from the TS query (which has the same mismatch).
 *
 * @return array<int, array<string, mixed>>
 */
function subjects_filter_by_year(int $year): array
{
    return array_map(
            'subjects_cast_row',
            Db::query(
        subjects_base_select() . ' WHERE `year` = ? AND `subjects`.`deleted_at` IS NULL ORDER BY subject_name',
        [$year]
    ));
}

/**
 * Port of Subjects.listAll.
 *
 * @return array<int, array<string, mixed>>
 */
function subjects_list_all(): array
{
    return array_map(
            'subjects_cast_row',
            Db::query(
        subjects_base_select() . ' WHERE `subjects`.`deleted_at` IS NULL ORDER BY subject_name'
    ));
}

/**
 * Port of Subjects.filterByClassDegree.
 *
 * @return array<int, array<string, mixed>>
 */
function subjects_filter_by_class_degree(string $degree, int $class_id): array
{
    return array_map(
            'subjects_cast_row',
            Db::query(
        subjects_base_select()
            . ' WHERE `subjects`.`degree` = ? AND `subjects`.`class` = ? AND `subjects`.`deleted_at` IS NULL'
            . ' ORDER BY subject_name',
        [$degree, $class_id]
    ));
}

/**
 * Port of Subjects.filterByClassDegreeGradingSystem (INNER JOIN grading_systems).
 *
 * @return array<int, array<string, mixed>>
 */
function subjects_filter_by_class_degree_grading_system(string $degree, int $class_id, string $grading_system_name): array
{
    return array_map(
            'subjects_cast_row',
            Db::query(
        subjects_base_select(true)
            . ' WHERE `subjects`.`degree` = ? AND `subjects`.`class` = ?'
            . ' AND `grading_systems`.`name` = ? AND `subjects`.`deleted_at` IS NULL'
            . ' ORDER BY subject_name',
        [$degree, $class_id, $grading_system_name]
    ));
}

/**
 * Port of Subjects.filterByDegree.
 *
 * @return array<int, array<string, mixed>>
 */
function subjects_filter_by_degree(string $degree): array
{
    return array_map(
            'subjects_cast_row',
            Db::query(
        subjects_base_select()
            . ' WHERE `subjects`.`degree` = ? AND `subjects`.`deleted_at` IS NULL'
            . ' ORDER BY subject_name',
        [$degree]
    ));
}

/**
 * Port of Subjects.findByName (single row or null).
 *
 * @return array<string, mixed>|null
 */
function subjects_find_by_name(string $name): ?array
{
    $row = Db::first(
        subjects_base_select()
            . ' WHERE `subjects`.`subject_normalized_name` = ? AND `subjects`.`deleted_at` IS NULL',
        [$name]
    );

    return $row === null ? null : subjects_cast_row($row);
}

/**
 * Port of Subjects.filterByTeacherClassDegree.
 *
 * @return array<int, array<string, mixed>>
 */
function subjects_filter_by_teacher_class_degree(int $teacher_id, string $degree, int $class_id): array
{
    return array_map(
            'subjects_cast_row',
            Db::query(
        subjects_base_select()
            . ' WHERE `subjects`.`teacher` = ? AND `subjects`.`degree` = ? AND `subjects`.`class` = ?'
            . ' AND `subjects`.`deleted_at` IS NULL'
            . ' ORDER BY subject_name',
        [$teacher_id, $degree, $class_id]
    ));
}

/**
 * Port of Subjects.filterByTeacherDegree.
 *
 * @return array<int, array<string, mixed>>
 */
function subjects_filter_by_teacher_degree(int $teacher_id, string $degree): array
{
    return array_map(
            'subjects_cast_row',
            Db::query(
        subjects_base_select()
            . ' WHERE `subjects`.`teacher` = ? AND `subjects`.`degree` = ?'
            . ' AND `subjects`.`deleted_at` IS NULL'
            . ' ORDER BY subject_name',
        [$teacher_id, $degree]
    ));
}

/**
 * Port of Subjects.filterByLabTeacher.
 *
 * @return array<int, array<string, mixed>>
 */
function subjects_filter_by_lab_teacher(int $lab_teacher_id): array
{
    return array_map(
            'subjects_cast_row',
            Db::query(
        subjects_base_select()
            . ' WHERE `subjects`.`lab_teacher` = ? AND `subjects`.`deleted_at` IS NULL'
            . ' ORDER BY subject_name',
        [$lab_teacher_id]
    ));
}

/**
 * Port of Subjects.autocomplete.
 * NOTE: like the TS query (marked "todo: account for soft delete"), no
 * deleted_at filter here.
 *
 * @return array<int, array<string, mixed>>
 */
function subjects_autocomplete(string $searched_name): array
{
    return Db::query(
        'SELECT subject_name, subject_normalized_name
         FROM subjects
         WHERE subject_normalized_name LIKE ?
         ORDER BY subject_name ASC LIMIT 10',
        ['%' . $searched_name . '%']
    );
}

/**
 * Port of Subjects.autocompleteStudentsBySubject (students JOIN studying).
 *
 * @return array<int, array<string, mixed>>
 */
function subjects_autocomplete_students_by_subject(string $searched_name, int $subject_id): array
{
    return Db::query(
        'SELECT STUDIER.student_name
         FROM students AS STUDIER
         JOIN `studying` AS STUDYING ON STUDIER.id = STUDYING.student
         WHERE STUDIER.student_normalized_name LIKE ?
         AND STUDYING.subject = ?
         ORDER BY STUDIER.student_name ASC LIMIT 10',
        ['%' . $searched_name . '%', $subject_id]
    );
}

/**
 * Port of studying.bulkInsertBySubject (used by newSubject; the studying
 * feature itself is ported in a later task, so it lives here for now).
 */
function studying_bulk_insert_by_subject(int $subject_id): void
{
    Db::execute(
        'INSERT INTO studying (teacher, student, subject, studying_year, hours_missed, grade_fields, is_submitted)
         SELECT
            sub.teacher,
            stu.id AS student,
            sub.id AS subject,
            YEAR(CURDATE()) AS studying_year,
            0 AS hours_missed,
            \'[]\' AS grade_fields,
            0 AS is_submitted
         FROM subjects sub
         JOIN students stu ON sub.class = stu.class AND sub.degree = stu.degree
         WHERE sub.id = ?
         AND NOT EXISTS (
            SELECT 1
            FROM studying existing
            WHERE existing.student = stu.id
            AND existing.subject = sub.id
            AND existing.studying_year = YEAR(CURDATE())
         )',
        [$subject_id]
    );
}

// ---------------------------------------------------------------------------
// Service helpers (subjects.service.ts)
// ---------------------------------------------------------------------------

/**
 * Port of ensureAllowedSubjectKeys.
 *
 * @param array<string, mixed> $data
 */
function subjects_ensure_allowed_keys(array $data): void
{
    foreach (array_keys($data) as $key) {
        if (!in_array($key, SUBJECTS_ALLOWED_KEYS, true)) {
            throw new Exception('invalid request: unexpected key ' . $key);
        }
    }
}

/**
 * Port of resolveHasLabFlag.
 */
function subjects_resolve_has_lab_flag(mixed $value): bool
{
    if (is_bool($value)) {
        return $value;
    }

    if (is_string($value)) {
        return $value === 'true';
    }

    return false;
}

/**
 * Port of resolveGradingSystemId: accepts grading_system_id (number) or
 * grading_system_name (string, normalized) and returns the systems id.
 *
 * @param array<string, mixed> $data
 */
function subjects_resolve_grading_system_id(array $data): int
{
    $gradingSystemId = $data['grading_system_id'] ?? null;

    if (is_int($gradingSystemId)) {
        $rows = grading_systems_fetch($gradingSystemId);
        $gradingSystem = $rows[0] ?? null;

        if ($gradingSystem === null) {
            throw new Exception('grading system not found');
        }

        return (int)$gradingSystem['id'];
    }

    $gradingSystemName = $data['grading_system_name'] ?? null;

    if (is_string($gradingSystemName)) {
        $gradingSystem = grading_systems_find_by_name(normalize_arabic($gradingSystemName));

        if ($gradingSystem === null) {
            throw new Exception('grading system not found');
        }

        return (int)$gradingSystem['id'];
    }

    throw new Exception('grading_system_id or grading_system_name is required');
}

// ---------------------------------------------------------------------------
// RPC handlers (subjects.service.ts)
// ---------------------------------------------------------------------------

function newSubject(Metadata $metadata, array $data)
{
    if (array_key_exists('lab_hours_weekly', $data) && !array_key_exists('lab_weekly_hours', $data)) {
        $data['lab_weekly_hours'] = $data['lab_hours_weekly'];
    }
    unset($data['lab_hours_weekly']);

    subjects_ensure_allowed_keys($data);
    validate_params($data, SUBJECTS_REQUIRED_KEYS);

    if (!is_string($data['teacher_name'] ?? null)) {
        throw new Exception('Unexpected error: teacher_name cannot be anythin but a string');
    }

    $data['semester'] = (int)($data['semester'] ?? 0);
    $data['class'] = (int)($data['class'] ?? 0);
    $data['hours_weekly'] = (int)($data['hours_weekly'] ?? 0);
    $data['lab_weekly_hours'] = (int)($data['lab_weekly_hours'] ?? 0);

    if ($data['semester'] < 1 || $data['semester'] > 2) {
        throw new Exception('Unexpected error: semester cannot be anythin but a number between 1 and 2');
    }

    if ($data['hours_weekly'] <= 0) {
        throw new Exception('Unexpected error: hours_weekly must be a positive number');
    }

    // check to which teacher the subject is assigned
    $teacher = teaching_staff_find_by_name(normalize_arabic($data['teacher_name']));

    if ($teacher === null) {
        throw new Exception('لم يتم العثور على التدريسي');
    }

    $data['teacher'] = (int)$teacher['id'];
    unset($data['teacher_name']);

    $data['grading_system_id'] = subjects_resolve_grading_system_id($data);
    unset($data['grading_system_name']);

    $hasLab = subjects_resolve_has_lab_flag($data['has_lab'] ?? null);
    if ($hasLab) {
        $labTeacherName = $data['lab_teacher_name'] ?? null;
        if (!is_string($labTeacherName) || trim($labTeacherName) === '') {
            throw new Exception('لم يتم تحديد تدريسي المختبر');
        }
        if (!is_int($data['max_lab_grade'] ?? null) || $data['max_lab_grade'] <= 0) {
            throw new Exception('درجة المختبر يجب ان تكون اكبر من صفر');
        }
        $labGradeField = $data['lab_grade_field'] ?? null;
        if (!is_string($labGradeField) || trim($labGradeField) === '') {
            throw new Exception('لم يتم تحديد حقل الدرجة');
        }
        if (!is_int($data['lab_weekly_hours']) || $data['lab_weekly_hours'] <= 0) {
            throw new Exception('عدد ساعات المختبر اسبوعياً يجب ان يكون اكبر من صفر');
        }

        $lab_teacher = teaching_staff_find_by_name(normalize_arabic($labTeacherName));
        if ($lab_teacher === null) {
            throw new Exception('لم يتم العثور على تدريسي المختبر');
        }

        $data['lab_teacher'] = (int)$lab_teacher['id'];
    } else {
        $data['lab_teacher'] = null;
        $data['max_lab_grade'] = null;
        $data['lab_grade_field'] = null;
        $data['lab_weekly_hours'] = 0;
    }

    unset($data['lab_teacher_name']);
    unset($data['has_lab']);

    $data['subject_normalized_name'] = normalize_arabic($data['subject_name']);
    $data['total_hours'] = ($data['hours_weekly'] + $data['lab_weekly_hours']) * 15;

    $existingSubject = subjects_find_by_name($data['subject_normalized_name']);
    if ($existingSubject !== null) {
        throw new Exception('المادة الدراسية موجودة بالفعل في النظام');
    }

    try {
        $subject = subjects_insert($data)[0] ?? null;
    } catch (PDOException $err) {
        if ($err->getCode() === '23000') { // unique violation
            throw new Exception('المادة الدراسية موجودة بالفعل في النظام');
        }

        throw new Exception('حدث خطأ أثناء إضافة المادة الدراسية');
    }

    if ($subject === null) {
        throw new Exception('حدث خطأ أثناء إضافة المادة الدراسية');
    }

    studying_bulk_insert_by_subject((int)$subject['id']);

    return (int)$subject['id'];
}

function updateSubject(Metadata $metadata, int $id, array $data)
{
    if (array_key_exists('lab_hours_weekly', $data) && !array_key_exists('lab_weekly_hours', $data)) {
        $data['lab_weekly_hours'] = $data['lab_hours_weekly'];
    }
    unset($data['lab_hours_weekly']);

    unset($data['name']);
    unset($data['id']);

    loose_validate_params($data, [
        'subject_name', 'degree', 'class', 'total_hours', 'hours_weekly',
        'is_attending_required', 'teacher_name', 'semester', 'grading_system_name',
    ]);

    $updateData = [
        'subject_name' => $data['subject_name'] ?? null,
        'degree' => $data['degree'] ?? null,
        'class' => $data['class'] ?? null,
        'hours_weekly' => $data['hours_weekly'] ?? null,
        'is_attending_required' => $data['is_attending_required'] ?? null,
        'teacher_name' => $data['teacher_name'] ?? null,
        'semester' => $data['semester'] ?? null,
        'grading_system_name' => $data['grading_system_name'] ?? null,
        'has_lab' => $data['has_lab'] ?? null,
        'lab_teacher_name' => $data['lab_teacher_name'] ?? null,
        'max_lab_grade' => $data['max_lab_grade'] ?? null,
        'lab_grade_field' => $data['lab_grade_field'] ?? null,
        'lab_weekly_hours' => $data['lab_weekly_hours'] ?? null,
    ];

    $updateData['semester'] = (int)($updateData['semester'] ?? 0);
    $updateData['hours_weekly'] = (int)($updateData['hours_weekly'] ?? 0);
    $updateData['lab_weekly_hours'] = (int)($updateData['lab_weekly_hours'] ?? 0);

    $updateData['subject_normalized_name'] = normalize_arabic($updateData['subject_name']);

    $teacherName = $updateData['teacher_name'] ?? null;
    if (!is_string($teacherName) || trim($teacherName) === '') {
        throw new Exception('Teacher not found');
    }

    // check to which teacher the subject is assigned
    $teacher = teaching_staff_find_by_name(normalize_arabic($teacherName));
    if ($teacher === null) {
        throw new Exception('Teacher not found');
    }

    $updateData['teacher'] = (int)$teacher['id'];
    unset($updateData['teacher_name']);

    $updateData['grading_system_id'] = subjects_resolve_grading_system_id($updateData);
    unset($updateData['grading_system_name']);

    $hasLab = subjects_resolve_has_lab_flag($updateData['has_lab']);
    if ($hasLab) {
        $labTeacherName = $updateData['lab_teacher_name'] ?? null;
        if (!is_string($labTeacherName) || trim($labTeacherName) === '') {
            throw new Exception('لم يتم تحديد تدريسي المختبر');
        }
        $maxLabGrade = $updateData['max_lab_grade'] ?? null;
        if (!is_int($maxLabGrade) && !is_float($maxLabGrade)) {
            throw new Exception('درجة المختبر يجب ان تكون اكبر من صفر');
        }
        if ($maxLabGrade <= 0) {
            throw new Exception('درجة المختبر يجب ان تكون اكبر من صفر');
        }
        $labGradeField = $updateData['lab_grade_field'] ?? null;
        if (!is_string($labGradeField) || trim($labGradeField) === '') {
            throw new Exception('لم يتم تحديد حقل الدرجة');
        }
        if ($updateData['lab_weekly_hours'] <= 0) {
            throw new Exception('عدد ساعات المختبر اسبوعياً يجب ان يكون اكبر من صفر');
        }

        $lab_teacher = teaching_staff_find_by_name(normalize_arabic($labTeacherName));
        if ($lab_teacher === null) {
            throw new Exception('لم يتم العثور على تدريسي المختبر');
        }

        $updateData['lab_teacher'] = (int)$lab_teacher['id'];
    } else {
        $updateData['lab_teacher'] = null;
        $updateData['max_lab_grade'] = null;
        $updateData['lab_grade_field'] = null;
        $updateData['lab_weekly_hours'] = 0;
    }

    if ($updateData['hours_weekly'] <= 0) {
        throw new Exception('Unexpected error: hours_weekly must be a positive number');
    }

    unset($updateData['lab_teacher_name']);
    unset($updateData['has_lab']);

    $updateData['total_hours'] = ($updateData['hours_weekly'] + $updateData['lab_weekly_hours']) * 15;

    if ($updateData['semester'] < 1 || $updateData['semester'] > 2) {
        throw new Exception('Unexpected error: semester cannot be anythin but a number between 1 and 2');
    }

    subjects_update($id, $updateData);
    return $id;
}

function deleteSubject(Metadata $metadata, int $id): void
{
    subjects_delete($id);
}

function fetchSingleSubject(Metadata $metadata, int $id)
{
    $rows = subjects_fetch($id);
    $result = $rows[0] ?? null;

    if ($result === null) {
        throw new Exception('no subject found');
    }

    return $result;
}

function fetchSubjects(Metadata $metadata): array
{
    $result = subjects_list_all();

    if (!is_array($result)) {
        throw new Exception('no subjects found');
    }

    return $result;
}

function filterSubjectsByClassDegree(Metadata $metadata, string $degree, int $subject_class): array
{
    $result = subjects_filter_by_class_degree($degree, $subject_class);

    if (!is_array($result)) {
        throw new Exception('no subjects found');
    }

    return $result;
}

function filterSubjectsByDegree(Metadata $metadata, string $degree): array
{
    $result = subjects_filter_by_degree($degree);

    if (!is_array($result)) {
        throw new Exception('no subjects found');
    }

    return $result;
}

/**
 * @return array<int, string>
 */
function autocompleteSubject(Metadata $metadata, string $name): array
{
    $result = subjects_autocomplete($name);

    if (!is_array($result)) {
        throw new Exception('no subjects found');
    }

    $flattened_result = [];
    foreach ($result as $user) {
        $flattened_result[] = $user['subject_name'];
    }

    return $flattened_result;
}

function findSubjectByName(Metadata $metadata, string $name)
{
    $result = subjects_find_by_name($name);

    if ($result === null) {
        throw new Exception('no subjects found');
    }

    return $result;
}

/**
 * @return array<int, string>
 */
function autocompleteStudentsBySubject(Metadata $metadata, string $searched_name, int $subject_id): array
{
    $result = subjects_autocomplete_students_by_subject($searched_name, $subject_id);

    if (!is_array($result)) {
        return [];
    }

    $flattened_result = [];
    foreach ($result as $user) {
        $flattened_result[] = $user['student_name'];
    }

    return $flattened_result;
}

function fetchSubjectsByTeacher(Metadata $metadata, string $degree, ?int $subject_class = null): array
{
    $uid = $metadata->auth['user_id'] ?? null;

    $teacher = is_int($uid) ? teaching_staff_fetch_by_user_id($uid) : null;
    if ($teacher === null) {
        throw new Exception('teacher not authorized');
    }

    if (!empty($subject_class)) {
        $result = subjects_filter_by_teacher_class_degree((int)$teacher['id'], $degree, $subject_class);
        if (!is_array($result)) {
            throw new Exception('no subjects found');
        }
    } else {
        $result = subjects_filter_by_teacher_degree((int)$teacher['id'], $degree);
        if (!is_array($result)) {
            throw new Exception('no subjects found');
        }
    }

    return $result;
}

function fetchSubjectsByLabTeacher(Metadata $metadata): array
{
    $uid = $metadata->auth['user_id'] ?? null;

    $lab_teacher = is_int($uid) ? teaching_staff_fetch_by_user_id($uid) : null;
    if ($lab_teacher === null) {
        throw new Exception('لم يتم العثور على التدريسي');
    }

    $result = subjects_filter_by_lab_teacher((int)$lab_teacher['id']);
    if (!is_array($result)) {
        throw new Exception('لم يتم العثور على اي مادة');
    }

    return $result;
}
