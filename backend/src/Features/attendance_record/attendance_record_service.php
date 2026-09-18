<?php

declare(strict_types=1);

/**
 * Port of backend/src/Features/attendance_record/attendance_record.sql.ts
 * (AttendanceRecord) and attendance_record.service.ts.
 *
 * Table helpers become plain functions with raw SQL (Cenusis\Db\Db); the RPC
 * handlers keep the exact TS export names and are registered by
 * attendanceRecordLoader() in attendance_record_loader.php.
 */

use Cenusis\Db\Db;
use Cenusis\Rpc\Metadata;

require_once __DIR__ . '/../subjects/subjects_service.php';
require_once __DIR__ . '/../teaching_staff/teaching_staff_service.php';

/** pg-norm visibles for attendance_record (SELECT list). */
const ATTENDANCE_RECORD_VISIBLES = ['id', 'subject', 'date', 'created_at', 'lab_attendance'];

use function Cenusis\Helpers\row_cast;

/**
 * Cast lab_attendance back to a real boolean (PostgreSQL wire shape).
 */
function attendance_record_cast_row(array $row): array
{
    return row_cast($row, ['lab_attendance']);
}

// ---------------------------------------------------------------------------
// Table helpers (attendance_record.sql.ts)
// ---------------------------------------------------------------------------

/**
 * Port of PG_Table.insert (RETURNING id -> lastInsertId).
 *
 * @param array<string, mixed> $data
 * @return array<int, array<string, mixed>>
 */
function attendance_record_insert(array $data): array
{
    foreach (array_keys($data) as $key) {
        if (!in_array($key, ATTENDANCE_RECORD_VISIBLES, true)) {
            throw new Exception('inserted rows need to have all columns in visibles');
        }
    }

    // Column names are backtick-quoted (`date` is reserved in MySQL).
    $columns = array_keys($data);
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    ['lastInsertId' => $lastInsertId] = Db::execute(
        'INSERT INTO attendance_record (`' . implode('`, `', $columns) . '`) VALUES (' . $placeholders . ')',
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
function attendance_record_fetch(int $row_id): array
{
    return array_map(
        'attendance_record_cast_row',
        Db::query(
            'SELECT ' . implode(', ', ATTENDANCE_RECORD_VISIBLES)
                . ' FROM attendance_record WHERE id = ?',
            [$row_id]
        )
    );
}

/**
 * Port of AttendanceRecord.findBySubject.
 *
 * @return array<int, array<string, mixed>>
 */
function attendance_record_find_by_subject(int $subject_id): array
{
    return array_map(
        'attendance_record_cast_row',
        Db::query(
            'SELECT ' . implode(', ', ATTENDANCE_RECORD_VISIBLES)
                . ' FROM attendance_record WHERE subject = ? AND lab_attendance = FALSE',
            [$subject_id]
        )
    );
}

/**
 * Port of AttendanceRecord.findByLabSubject.
 *
 * @return array<int, array<string, mixed>>
 */
function attendance_record_find_by_lab_subject(int $subject_id): array
{
    return array_map(
        'attendance_record_cast_row',
        Db::query(
            'SELECT ' . implode(', ', ATTENDANCE_RECORD_VISIBLES)
                . ' FROM attendance_record WHERE subject = ? AND lab_attendance = TRUE',
            [$subject_id]
        )
    );
}

/**
 * Port of AttendanceRecord.delete.
 *
 * @return array<int, array<string, mixed>>
 */
function attendance_record_delete(int $attendance_record_id): array
{
    Db::execute('DELETE FROM attendance_record WHERE id = ?', [$attendance_record_id]);
    return [];
}

/**
 * Port of AttendanceRecord.fetchWithSubject (single row or null).
 *
 * @return array<string, mixed>|null
 */
function attendance_record_fetch_with_subject(int $attendance_record_id): ?array
{
    $row = Db::first(
        'SELECT ar.id, ar.date, ar.lab_attendance, s.subject_name
        FROM attendance_record ar
        JOIN subjects s ON ar.subject = s.id
        WHERE ar.id = ?',
        [$attendance_record_id]
    );

    return $row === null ? null : row_cast($row, ['lab_attendance']);
}

// ---------------------------------------------------------------------------
// RPC handlers (attendance_record.service.ts)
// ---------------------------------------------------------------------------

function createDailyAttendanceRecord(Metadata $metadata, int $subject_id, string $date, bool $lab_attendance = false)
{
    if (!is_int($subject_id)) {
        throw new Exception('subject_id must be a number');
    }

    if (!$lab_attendance) {
        $lab_attendance = false;
    }

    // access control
    $uid = $metadata->auth['user_id'] ?? null;
    if (!is_int($uid)) {
        throw new Exception('قم بتسجيل الدخول اولاً');
    }

    $teacher = teaching_staff_fetch_by_user_id($uid);
    if ($teacher === null) {
        throw new Exception('teacher not authorized');
    }

    $subjects = subjects_fetch($subject_id);
    $subject = $subjects[0] ?? null;
    if ($subject === null || !empty($subject['deleted_at'])) {
        throw new Exception('Subject not found');
    }

    if ($lab_attendance) {
        if ((int)$subject['lab_teacher'] !== (int)$teacher['id']) {
            throw new Exception('ليس لديك صلاحية لانشاء  سجل غياب جديد لهذه مادة');
        }
    } else {
        if ((int)$subject['teacher'] !== (int)$teacher['id']) {
            throw new Exception('ليس لديك صلاحية لانشاء سجل غياب جديد لهذه مادة');
        }
    }

    return attendance_record_insert([
        'subject' => $subject_id,
        'date' => $date,
        'lab_attendance' => $lab_attendance,
    ]);
}

function fetchDailyAttendanceRecordsForTheSubject(Metadata $metadata, int $subject_id): array
{
    if (!is_int($subject_id)) {
        throw new Exception('subject_id must be a number');
    }

    // access control
    $uid = $metadata->auth['user_id'] ?? null;
    if (!is_int($uid)) {
        throw new Exception('قم بتسجيل الدخول اولاً');
    }

    if (($metadata->auth['role'] ?? null) === 'teacher') {
        $teacher = teaching_staff_fetch_by_user_id($uid);
        if ($teacher === null) {
            throw new Exception('teacher not authorized');
        }
        $subjects = subjects_fetch($subject_id);
        $subject = $subjects[0] ?? null;
        if ($subject === null || !empty($subject['deleted_at'])) {
            throw new Exception('Subject not found');
        }
        if ((int)$subject['teacher'] !== (int)$teacher['id']) {
            throw new Exception('ليس لديك صلاحية للوصول لسجل الحضور لهذه المادة');
        }
    }

    return attendance_record_find_by_subject($subject_id);
}

function fetchDailyLabAttendanceRecordsForTheSubject(Metadata $metadata, int $subject_id): array
{
    if (!is_int($subject_id)) {
        throw new Exception('subject_id must be a number');
    }

    $uid = $metadata->auth['user_id'] ?? null;
    if (!is_int($uid)) {
        throw new Exception('قم بتسجيل الدخول اولاً');
    }

    if (($metadata->auth['role'] ?? null) === 'teacher') {
        $teacher = teaching_staff_fetch_by_user_id($uid);
        if ($teacher === null) {
            throw new Exception('teacher not authorized');
        }

        $subjects = subjects_fetch($subject_id);
        $subject = $subjects[0] ?? null;
        if ($subject === null || !empty($subject['deleted_at'])) {
            throw new Exception('Subject not found');
        }

        if ((int)$subject['lab_teacher'] !== (int)$teacher['id']) {
            throw new Exception('ليس لديك صلاحية للوصول لسجل حضور المختبر لهذه المادة');
        }
    }

    return attendance_record_find_by_lab_subject($subject_id);
}

function fetchAttendanceRecordWithSubject(Metadata $metadata, int $attendance_record_id)
{
    if (!is_int($attendance_record_id)) {
        throw new Exception('attendance_record_id must be a number');
    }

    // IDOR guard: resolve the record's subject and assert access
    $result = attendance_record_fetch_with_subject($attendance_record_id);
    if ($result === null) {
        throw new Exception('Attendance record not found');
    }

    if (($metadata->auth['role'] ?? null) === 'teacher') {
        $uid = $metadata->auth['user_id'] ?? null;
        if (!is_int($uid)) {
            throw new Exception('قم بتسجيل الدخول اولاً');
        }
        $teacher = teaching_staff_fetch_by_user_id($uid);
        if ($teacher === null) {
            throw new Exception('teacher not authorized');
        }
        $records = attendance_record_fetch($attendance_record_id);
        $record = $records[0] ?? null;
        if ($record === null) {
            throw new Exception('Attendance record not found');
        }
        $subjects = subjects_fetch((int)$record['subject']);
        $subject = $subjects[0] ?? null;
        if ($subject === null) {
            throw new Exception('Subject not found');
        }
        if ((int)$subject['teacher'] !== (int)$teacher['id']
            && (int)($subject['lab_teacher'] ?? 0) !== (int)$teacher['id']
        ) {
            throw new Exception('ليس لديك صلاحية للوصول لسجل الحضور لهذه المادة');
        }
    }

    return $result;
}
