<?php

declare(strict_types=1);

/**
 * Port of backend/src/Features/studying/studying.sql.ts (Studying)
 * and studying.service.ts.
 *
 * Table helpers become plain functions with raw SQL (Cenusis\Db\Db); the RPC
 * handlers keep the exact TS export names and are registered by
 * studyingLoader() in studying_loader.php.
 */

use Cenusis\Db\Db;
use Cenusis\Rpc\Metadata;
use function Cenusis\Helpers\validate_params;
use function Cenusis\Helpers\loose_validate_params;
use function Cenusis\Helpers\row_cast;

/** pg-norm visibles for studying (SELECT list). */
const STUDYING_VISIBLES = [
    'id', 'teacher', 'student', 'subject', 'exam_retakes',
    'semester_retakes', 'studying_year', 'hours_missed',
    'grade_fields', 'is_attending_required', 'is_submitted', 'alert_level',
];

/** MySQL schema adds lab_grade, allowed as a write fillable. */
const STUDYING_FILLABLES = [
    'id', 'teacher', 'student', 'subject', 'exam_retakes',
    'semester_retakes', 'studying_year', 'hours_missed',
    'grade_fields', 'is_attending_required', 'is_submitted', 'alert_level',
    'lab_grade',
];

// ---------------------------------------------------------------------------
// Table helpers (studying.sql.ts)
// ---------------------------------------------------------------------------

/**
 * Port of PG_Table.insert (RETURNING id -> lastInsertId).
 *
 * @param array<string, mixed> $data
 * @return array<int, array<string, mixed>>
 */
function studying_insert(array $data): array
{
    foreach (array_keys($data) as $key) {
        if (!in_array($key, STUDYING_FILLABLES, true)) {
            throw new Exception('inserted rows need to have all columns in visibles');
        }
    }

    $columns = array_keys($data);
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    ['lastInsertId' => $lastInsertId] = Db::execute(
        'INSERT INTO studying (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')',
        array_values($data)
    );

    $id = array_key_exists('id', $data) ? (int)$data['id'] : (int)$lastInsertId;
    return [['id' => $id]];
}

/**
 * Port of PG_Table.update. Validates keys, returns the affected id rows.
 *
 * @param array<string, mixed> $data
 * @return array<int, array<string, mixed>>
 */
function studying_update(int $row_id, array $data): array
{
    foreach (array_keys($data) as $key) {
        if (!in_array($key, STUDYING_FILLABLES, true)) {
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

    Db::execute('UPDATE studying SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);
    return [['id' => $row_id]];
}

/**
 * Port of PG_Table.delete.
 *
 * @return array<int, array<string, mixed>>
 */
function studying_delete(int $row_id): array
{
    Db::execute('DELETE FROM studying WHERE id = ?', [$row_id]);
    return [];
}

/**
 * Port of PG_Table.fetch.
 *
 * @return array<int, array<string, mixed>>
 */
function studying_fetch(int $row_id): array
{
    return Db::query(
        'SELECT ' . implode(', ', STUDYING_VISIBLES) . ' FROM studying WHERE id = ?',
        [$row_id]
    );
}

/**
 * Port of Studying.findBySubject.
 *
 * @return array<int, array<string, mixed>>
 */
function studying_find_by_subject(int $subject_id): array
{
    return array_map(
        'studying_decode_row',
        Db::query(
            'SELECT
                STUDYING.*,
                STUDENT.student_name,
                STUDENT.degree,
                STUDENT.class
            FROM studying AS STUDYING
            JOIN students AS STUDENT
                ON STUDYING.student = STUDENT.id
            WHERE STUDYING.subject = ?',
            [$subject_id]
        )
    );
}

/**
 * MySQL JSON columns are returned as strings; postgres.js parsed JSONB
 * automatically, so decode grade_fields back into a PHP array on read.
 *
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function studying_decode_row(array $row): array
{
    if (isset($row['grade_fields']) && is_string($row['grade_fields'])) {
        $decoded = json_decode($row['grade_fields'], true);
        $row['grade_fields'] = is_array($decoded) ? $decoded : $row['grade_fields'];
    }

    $row = row_cast(
        $row,
        ['is_attending_required', 'is_submitted'],
        ['absence_ratio_percent']
    );

    return $row;
}

// NOTE: Studying.bulkInsertBySubject is already ported as
// studying_bulk_insert_by_subject() in subjects_service.php, so it is not
// re-declared here.

/**
 * Port of Studying.setGradeFields.
 *
 * @param array<int, array{name: string, grade: mixed}> $grade_fields
 */
function studying_set_grade_fields(array $grade_fields, int $subject_id, int $student_id): void
{
    Db::execute(
        'UPDATE studying SET grade_fields = ? WHERE subject = ? AND student = ?',
        [json_encode($grade_fields), $subject_id, $student_id]
    );
}

/**
 * Port of Studying.getGradeFields.
 *
 * @return array<int, array<string, mixed>>
 */
function studying_get_grade_fields(int $subject_id): array
{
    return array_map(
        'studying_decode_row',
        Db::query(
            'SELECT
                STUDYING.id,
                STUDENT.student_name,
                SUBJECT.subject_name,
                TEACHER.teacher_name,
                STUDYING.grade_fields
            FROM studying AS STUDYING
            JOIN students AS STUDENT
                ON STUDYING.student = STUDENT.id
            JOIN subjects AS SUBJECT
                ON STUDYING.subject = SUBJECT.id
            JOIN teaching_staff AS TEACHER
                ON STUDYING.teacher = TEACHER.id
            WHERE STUDYING.subject = ?',
            [$subject_id]
        )
    );
}

/**
 * Port of Studying.getLabGrades.
 *
 * @return array<int, array<string, mixed>>
 */
function studying_get_lab_grades(int $subject_id): array
{
    return Db::query(
        'SELECT
            STUDYING.id,
            STUDENT.student_name,
            SUBJECT.subject_name,
            TEACHER.teacher_name,
            STUDYING.lab_grade
        FROM studying AS STUDYING
        JOIN students AS STUDENT
            ON STUDYING.student = STUDENT.id
        JOIN subjects AS SUBJECT
            ON STUDYING.subject = SUBJECT.id
        JOIN teaching_staff AS TEACHER
            ON STUDYING.teacher = TEACHER.id
        WHERE STUDYING.subject = ?',
        [$subject_id]
    );
}

/**
 * Port of Studying.getGradesByClassDegree.
 *
 * @return array<int, array<string, mixed>>
 */
function studying_get_grades_by_class_degree(string $degree, int $class_number, string $grading_system): array
{
    return array_map(
        'studying_decode_row',
        Db::query(
            'SELECT
                STUDENT.id AS student_id,
                STUDENT.student_name,
                SUBJECT.id AS subject_id,
                SUBJECT.subject_name,
                STUDYING.grade_fields,
                STUDYING.lab_grade
            FROM studying AS STUDYING
            JOIN students AS STUDENT ON STUDYING.student = STUDENT.id
            JOIN subjects AS SUBJECT ON STUDYING.subject = SUBJECT.id
            JOIN grading_systems AS gs ON SUBJECT.grading_system_id = gs.id
            WHERE SUBJECT.degree = ?
              AND SUBJECT.class = ?
              AND SUBJECT.deleted_at IS NULL
              AND gs.name = ?
            ORDER BY STUDENT.student_name, SUBJECT.subject_name',
            [$degree, $class_number, $grading_system]
        )
    );
}

/**
 * Port of Studying.fetchAbsenceAlertCandidates.
 *
 * @return array<int, array<string, mixed>>
 */
function studying_fetch_absence_alert_candidates(): array
{
    return Db::query(
        'SELECT
            STUDYING.id AS studying_id,
            STUDYING.hours_missed,
            STUDYING.alert_level,
            STUDENT.student_name,
            SUBJECT.subject_name,
            SUBJECT.total_hours,
            SUBJECT.grading_system_id,
            GS.name AS grading_system_name
        FROM studying AS STUDYING
        JOIN students AS STUDENT
            ON STUDYING.student = STUDENT.id
        JOIN subjects AS SUBJECT
            ON STUDYING.subject = SUBJECT.id
        JOIN grading_systems AS GS
            ON SUBJECT.grading_system_id = GS.id
        WHERE SUBJECT.deleted_at IS NULL'
    );
}

/**
 * Port of Studying.setAlertLevel.
 */
function studying_set_alert_level(int $studying_id, ?string $alert_level): void
{
    Db::execute(
        'UPDATE studying SET alert_level = ? WHERE id = ?',
        [$alert_level, $studying_id]
    );
}

/**
 * Port of Studying.fetchAbsenceAlerts. The TS version has one query per
 * filter combination; a single query with dynamically built WHERE covers
 * all six branches with identical filtering and ordering.
 *
 * @return array<int, array<string, mixed>>
 */
function studying_fetch_absence_alerts(
    ?string $degree = null,
    ?int $class_number = null,
    ?string $grading_system_name = null
): array {
    $hasDegree = is_string($degree) && trim($degree) !== '';
    $hasClass = is_int($class_number);
    $hasGradingSystem = is_string($grading_system_name) && trim($grading_system_name) !== '';

    $conditions = ['STUDYING.alert_level IS NOT NULL', 'SUBJECT.deleted_at IS NULL'];
    $params = [];

    if ($hasDegree) {
        $conditions[] = 'STUDENT.degree = ?';
        $conditions[] = 'SUBJECT.degree = ?';
        $params[] = trim((string)$degree);
        $params[] = trim((string)$degree);
    }

    // NOTE: in the TS branch structure the class filter only ever appears
    // together with degree (class-only combos fall through to the
    // unfiltered query), so it is gated on degree here as well.
    if ($hasDegree && $hasClass) {
        $conditions[] = 'STUDENT.class = ?';
        $conditions[] = 'SUBJECT.class = ?';
        $params[] = $class_number;
        $params[] = $class_number;
    }

    if ($hasGradingSystem) {
        $conditions[] = 'GS.name = ?';
        $params[] = trim((string)$grading_system_name);
    }

    return array_map(
        'studying_decode_row',
        Db::query(
            'SELECT
                STUDYING.id AS studying_id,
                STUDENT.student_name,
                SUBJECT.subject_name,
                STUDYING.hours_missed,
                SUBJECT.total_hours,
                CASE
                    WHEN SUBJECT.total_hours > 0
                        THEN (STUDYING.hours_missed / SUBJECT.total_hours) * 100
                    ELSE 0
                END AS absence_ratio_percent,
                STUDYING.alert_level,
                GS.name AS grading_system_name
            FROM studying AS STUDYING
            JOIN students AS STUDENT
                ON STUDYING.student = STUDENT.id
            JOIN subjects AS SUBJECT
                ON STUDYING.subject = SUBJECT.id
            JOIN grading_systems AS GS
                ON SUBJECT.grading_system_id = GS.id
            WHERE ' . implode(' AND ', $conditions) . '
            ORDER BY STUDENT.student_name, SUBJECT.subject_name',
            $params
        )
    );
}

// ---------------------------------------------------------------------------
// RPC handlers (studying.service.ts)
// ---------------------------------------------------------------------------

use function Cenusis\Access\assert_subject_access;
use function Cenusis\Access\teacher_for_user;

/**
 * Resolve the enrollment's subject and assert the caller may access it
 * (teachers are restricted to subjects they are whitelisted on).
 *
 * @return array<string, mixed> the studying row
 */
function studying_assert_enrollment_access(Metadata $metadata, int $id): array
{
    $rows = studying_fetch($id);
    $row = $rows[0] ?? null;
    if ($row === null) {
        throw new Exception('no enrollment found');
    }

    assert_subject_access($metadata, (int)$row['subject']);

    return $row;
}

function newEnrollment(Metadata $metadata, array $data): void
{
    if (isset($data['teacher_name'])) {
        unset($data['teacher_name']);
    }
    if (isset($data['student_name'])) {
        unset($data['student_name']);
    }

    validate_params($data, [
        'teacher_id', 'student_id', 'subject_id', 'studying_year',
        'hours_missed',
    ]);

    // IDOR guard: teachers may only enroll students into subjects they are
    // whitelisted on, and the enrollment's teacher is forced to themselves
    // (the payload teacher_id is not trusted).
    $subject = assert_subject_access($metadata, (int)$data['subject_id']);
    if (($metadata->auth['role'] ?? null) === 'teacher') {
        $uid = $metadata->auth['user_id'] ?? null;
        $teacher = is_int($uid) ? \Cenusis\Access\teacher_for_user($uid) : null;
        if ($teacher === null) {
            throw new Exception('teacher not authorized');
        }
        $data['teacher_id'] = (int)$teacher['id'];
    }

    $data['teacher'] = $data['teacher_id'];
    $data['student'] = $data['student_id'];
    $data['subject'] = $data['subject_id'];

    unset($data['teacher_id'], $data['student_id'], $data['subject_id']);

    // check to which teacher the subject is assigned
    $studying_students = studying_insert($data);
    if ($studying_students === []) {
        throw new Exception('Teacher not found');
    }
}

function updateEnrollment(Metadata $metadata, int $id, array $data): int
{
    loose_validate_params($data, [
        'teacher_id', 'student_id', 'subject_id', 'studying_year',
        'hours_missed',
    ]);

    if (array_key_exists('studying_year', $data)
        && (!is_int($data['studying_year']) || $data['studying_year'] < 1)
    ) {
        throw new Exception('Unexpected error: studying_year cannot be anythin but a positive number');
    }

    // IDOR guard: the enrollment must belong to a subject whitelisted to the
    // caller. Teachers may not re-point an enrollment at another subject or
    // another teacher (both the raw *_id keys and the mapped keys are
    // stripped before the handler maps them back in).
    $existing = studying_assert_enrollment_access($metadata, $id);
    if (($metadata->auth['role'] ?? null) === 'teacher') {
        unset($data['teacher'], $data['teacher_id'], $data['subject'], $data['subject_id']);
    }

    if (isset($data['teacher_name'])) {
        unset($data['teacher_name']);
    }
    if (isset($data['student_name'])) {
        unset($data['student_name']);
    }

    if (array_key_exists('teacher_id', $data)) {
        $data['teacher'] = $data['teacher_id'];
    }
    if (array_key_exists('student_id', $data)) {
        $data['student'] = $data['student_id'];
    }
    if (array_key_exists('subject_id', $data)) {
        $data['subject'] = $data['subject_id'];
    }

    unset($data['teacher_id'], $data['student_id'], $data['subject_id']);

    studying_update($id, $data);
    return $id;
}

function deleteEnrollment(Metadata $metadata, int $id): void
{
    // IDOR guard
    studying_assert_enrollment_access($metadata, $id);
    studying_delete($id);
}

function fetchSingleEnrollment(Metadata $metadata, int $id)
{
    $result = studying_assert_enrollment_access($metadata, $id);

    if ($result === null) {
        throw new Exception('no enrollment found');
    }

    return $result;
}

function fetchEnrollmentsForSubject(Metadata $metadata, int $subject_id): array
{
    // IDOR guard
    assert_subject_access($metadata, $subject_id);

    $result = studying_find_by_subject($subject_id);

    if ($result === null) {
        throw new Exception('no enrollment found');
    }

    return $result;
}
