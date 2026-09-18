<?php

declare(strict_types=1);

/**
 * Port of backend/src/Features/absented/absented.sql.ts (Absented)
 * and absented.service.ts.
 *
 * Table helpers become plain functions with raw SQL (Cenusis\Db\Db); the RPC
 * handlers keep the exact TS export names and are registered by
 * absentedLoader() in absented_loader.php.
 *
 * The markAbsent/update/remove helpers run inside SERIALIZABLE transactions
 * (the TS port used sql.begin({ isolation: 'serializable' }); in MySQL the
 * isolation level must be set before BEGIN, which Db::transaction handles).
 */

use Cenusis\Db\Db;
use Cenusis\Rpc\Metadata;
use function Cenusis\Helpers\validate_params;

require_once __DIR__ . '/../attendance_record/attendance_record_service.php';
require_once __DIR__ . '/../students/students_service.php';
require_once __DIR__ . '/../../Access/Ownership.php';

use function Cenusis\Access\assert_subject_access;

/**
 * Resolve an absence's subject through its attendance record and assert the
 * caller may access it (teachers are restricted to subjects they are
 * whitelisted on).
 */
function absented_assert_record_access(Metadata $metadata, int $attendance_record_id): void
{
    $records = attendance_record_fetch($attendance_record_id);
    $record = $records[0] ?? null;
    if ($record === null) {
        throw new Exception('Attendance record not found');
    }

    assert_subject_access($metadata, (int)$record['subject']);
}

/** pg-norm visibles for absented (SELECT list). */
const ABSENTED_VISIBLES = ['id', 'attendance_record', 'student', 'hours_absent'];

// ---------------------------------------------------------------------------
// Table helpers (absented.sql.ts)
// ---------------------------------------------------------------------------

/**
 * Port of Absented.findByAttendanceRecord.
 *
 * @return array<int, array<string, mixed>>
 */
function absented_find_by_attendance_record(int $attendance_record_id): array
{
    return Db::query(
        'SELECT DISTINCT
            std.id AS id,
            std.id AS student_id,
            std.student_name,
            COALESCE(abse.hours_absent, 0) AS hours_absent
        FROM students std
        JOIN studying sub ON sub.student = std.id
        JOIN attendance_record att ON att.subject = sub.subject
        LEFT JOIN absented abse ON abse.student = std.id
            AND abse.attendance_record = att.id
        WHERE att.id = ?',
        [$attendance_record_id]
    );
}

/**
 * Port of Absented.markAbsent (transaction).
 */
function absented_mark_absent(int $student_id, int $attendance_record_id, int $hours_absent, bool $lab_attendance): void
{
    Db::transaction(static function () use ($student_id, $attendance_record_id, $hours_absent, $lab_attendance): void {

        $existingAbsence = Db::first(
            'SELECT hours_absent
            FROM absented
            WHERE student = ?
            AND attendance_record = ?',
            [$student_id, $attendance_record_id]
        );

        if ($existingAbsence !== null) {
            $record = Db::first(
                'select * from attendance_record ar join subjects s on ar.subject = s.id where ar.id = ?',
                [$attendance_record_id]
            );
            $hoursWeekly = (int)($record['hours_weekly'] ?? 0);
            $labHoursWeekly = (int)($record['lab_weekly_hours'] ?? 0);

            if ($lab_attendance) {
                $hours_absent = min($hours_absent, $labHoursWeekly);
            } else {
                $hours_absent = min($hours_absent, $hoursWeekly);
            }

            $oldHoursAbsent = (int)$existingAbsence['hours_absent'];
            $delta = $hours_absent - $oldHoursAbsent;

            Db::execute(
                'UPDATE absented
                SET hours_absent = ?
                WHERE student = ?
                AND attendance_record = ?',
                [$hours_absent, $student_id, $attendance_record_id]
            );

            if ($delta !== 0) {
                Db::execute(
                    'UPDATE studying
                    SET hours_missed = hours_missed + ?
                    WHERE student = ?
                    AND subject = (
                        SELECT subject
                        FROM attendance_record
                        WHERE id = ?
                    )',
                    [$delta, $student_id, $attendance_record_id]
                );
            }
            return;
        }

        Db::execute(
            'INSERT INTO absented (student, attendance_record, hours_absent)
            VALUES (?, ?, ?)',
            [$student_id, $attendance_record_id, $hours_absent]
        );

        Db::execute(
            'UPDATE studying
            SET hours_missed = hours_missed + ?
            WHERE student = ?
            AND subject = (
                SELECT subject
                FROM attendance_record
                WHERE id = ?
            )',
            [$hours_absent, $student_id, $attendance_record_id]
        );
    }, 'SERIALIZABLE');
}

/**
 * Port of Absented.markAbsentBulk (transaction).
 *
 * @param array<int, int> $student_ids
 */
function absented_mark_absent_bulk(array $student_ids, int $attendance_record_id, int $hours_absent, bool $lab_attendance): void
{
    Db::transaction(static function () use ($student_ids, $attendance_record_id, $hours_absent, $lab_attendance): void {

        $record = Db::first(
            'select * from attendance_record ar join subjects s on ar.subject = s.id where ar.id = ?',
            [$attendance_record_id]
        );
        $hoursWeekly = (int)($record['hours_weekly'] ?? 0);
        $labHoursWeekly = (int)($record['lab_weekly_hours'] ?? 0);

        if ($lab_attendance) {
            $cappedHoursAbsent = min($hours_absent, $labHoursWeekly);
        } else {
            $cappedHoursAbsent = min($hours_absent, $hoursWeekly);
        }

        foreach ($student_ids as $student_id) {
            $existingAbsence = Db::first(
                'SELECT hours_absent
                FROM absented
                WHERE student = ?
                AND attendance_record = ?',
                [$student_id, $attendance_record_id]
            );

            if ($existingAbsence !== null) {
                $oldHoursAbsent = (int)$existingAbsence['hours_absent'];
                $delta = $cappedHoursAbsent - $oldHoursAbsent;

                Db::execute(
                    'UPDATE absented
                    SET hours_absent = ?
                    WHERE student = ?
                    AND attendance_record = ?',
                    [$cappedHoursAbsent, $student_id, $attendance_record_id]
                );

                if ($delta !== 0) {
                    Db::execute(
                        'UPDATE studying
                        SET hours_missed = hours_missed + ?
                        WHERE student = ?
                        AND subject = (
                            SELECT subject
                            FROM attendance_record
                            WHERE id = ?
                        )',
                        [$delta, $student_id, $attendance_record_id]
                    );
                }
            } else {
                Db::execute(
                    'INSERT INTO absented (student, attendance_record, hours_absent)
                    VALUES (?, ?, ?)',
                    [$student_id, $attendance_record_id, $cappedHoursAbsent]
                );

                Db::execute(
                    'UPDATE studying
                    SET hours_missed = hours_missed + ?
                    WHERE student = ?
                    AND subject = (
                        SELECT subject
                        FROM attendance_record
                        WHERE id = ?
                    )',
                    [$cappedHoursAbsent, $student_id, $attendance_record_id]
                );
            }
        }
    }, 'SERIALIZABLE');
}

/**
 * Port of Absented.removeAbsence (transaction).
 */
function absented_remove_absence(int $absented_id): void
{
    Db::transaction(static function () use ($absented_id): void {
        // First, get the hours_absent value from the record to be deleted
        $result = Db::first(
            'SELECT hours_absent, student, attendance_record
            FROM absented
            WHERE id = ?',
            [$absented_id]
        );

        // If no record found, exit or handle error
        if ($result === null) {
            throw new Exception('Absented record not found');
        }

        // Delete the absence record
        Db::execute('DELETE FROM absented WHERE id = ?', [$absented_id]);

        // Subtract the absent hours from studying.hours_missed
        Db::execute(
            'UPDATE studying
            SET hours_missed = hours_missed - ?
            WHERE student = ?
            AND subject = (
                SELECT subject
                FROM attendance_record
                WHERE id = ?
            )',
            [$result['hours_absent'], $result['student'], $result['attendance_record']]
        );
    }, 'SERIALIZABLE');
}

/**
 * Port of Absented.updateAbsence (transaction).
 */
function absented_update_absence(int $absented_id, int $hours_absent): void
{
    Db::transaction(static function () use ($absented_id, $hours_absent): void {

        $result = Db::first(
            'SELECT hours_absent, student, attendance_record
            FROM absented
            WHERE id = ?',
            [$absented_id]
        );

        if ($result === null) {
            throw new Exception('Absented record not found');
        }

        Db::execute(
            'UPDATE absented
            SET hours_absent = ?
            WHERE id = ?',
            [$hours_absent, $absented_id]
        );

        $delta = $hours_absent - (int)$result['hours_absent'];
        if ($delta !== 0) {
            Db::execute(
                'UPDATE studying
                SET hours_missed = hours_missed + ?
                WHERE student = ?
                AND subject = (
                    SELECT subject
                    FROM attendance_record
                    WHERE id = ?
                )',
                [$delta, $result['student'], $result['attendance_record']]
            );
        }
    }, 'SERIALIZABLE');
}

// ---------------------------------------------------------------------------
// RPC handlers (absented.service.ts)
// ---------------------------------------------------------------------------

function markStudentAbsent(Metadata $metadata, array $data, bool $lab_attendance): void
{
    validate_params($data, ['attendance_record_id', 'student_id', 'hours_absent']);

    $attendance_record_id = $data['attendance_record_id'];
    $student_id = $data['student_id'];
    $hours_absent = $data['hours_absent'];

    if (!is_int($student_id)) {
        throw new Exception('student_id must be a valid number');
    }

    if (!is_int($hours_absent)) {
        throw new Exception('hours_absent must be a valid number');
    }

    // IDOR guard: the record's subject must be whitelisted to the caller
    absented_assert_record_access($metadata, (int)$attendance_record_id);

    // Fetch attendance record to get the studying ID
    $records = attendance_record_fetch((int)$attendance_record_id);
    $record = $records[0] ?? null;
    if ($record === null) {
        throw new Exception('Attendance record not found');
    }

    // Fetch studying record to get the student ID
    $student_records = students_fetch($student_id);
    $student_record = $student_records[0] ?? null;
    if ($student_record === null) {
        throw new Exception('Student not found');
    }

    absented_mark_absent($student_id, (int)$attendance_record_id, $hours_absent, $lab_attendance);
}

function fetchAbsentStudents(Metadata $metadata, int $attendance_record_id): array
{
    // IDOR guard
    absented_assert_record_access($metadata, $attendance_record_id);

    return absented_find_by_attendance_record($attendance_record_id);
}

function removeAbsence(Metadata $metadata, ?int $absented_id): void
{
    if (!$absented_id || !is_int($absented_id)) {
        throw new Exception('Absented ID is required');
    }

    // IDOR guard: resolve the absence -> attendance record -> subject
    $absentedRow = Db::first('SELECT attendance_record FROM absented WHERE id = ?', [$absented_id]);
    if ($absentedRow === null) {
        throw new Exception('Attendance record not found');
    }
    absented_assert_record_access($metadata, (int)$absentedRow['attendance_record']);

    absented_remove_absence($absented_id);
}

function updateAbsence(Metadata $metadata, array $data): void
{
    validate_params($data, ['absented_id', 'hours_absent']);

    $absented_id = $data['absented_id'];
    $hours_absent = $data['hours_absent'];

    if (!is_int($absented_id)) {
        throw new Exception('Absented ID is required');
    }

    if (!is_int($hours_absent)) {
        throw new Exception('hours_absent must be a valid number');
    }

    // IDOR guard: resolve the absence -> attendance record -> subject
    $absentedRow = Db::first('SELECT attendance_record FROM absented WHERE id = ?', [$absented_id]);
    if ($absentedRow === null) {
        throw new Exception('Attendance record not found');
    }
    absented_assert_record_access($metadata, (int)$absentedRow['attendance_record']);

    absented_update_absence($absented_id, $hours_absent);
}

function markStudentAbsentBulk(Metadata $metadata, array $data, bool $lab_attendance): void
{
    validate_params($data, ['attendance_record_id', 'student_ids', 'hours_absent']);

    $attendance_record_id = $data['attendance_record_id'];
    $student_ids = $data['student_ids'];
    $hours_absent = $data['hours_absent'];

    if (!is_array($student_ids) || count($student_ids) === 0) {
        throw new Exception('student_ids must be a non-empty array');
    }

    if (!is_int($hours_absent)) {
        throw new Exception('hours_absent must be a valid number');
    }

    // IDOR guard
    absented_assert_record_access($metadata, (int)$attendance_record_id);

    $records = attendance_record_fetch((int)$attendance_record_id);
    $record = $records[0] ?? null;
    if ($record === null) {
        throw new Exception('Attendance record not found');
    }

    foreach ($student_ids as $student_id) {
        if (!is_int($student_id)) {
            throw new Exception('Each student_id must be a valid number');
        }

        $student_records = students_fetch($student_id);
        if ($student_records === []) {
            throw new Exception('Student ' . $student_id . ' not found');
        }
    }

    absented_mark_absent_bulk($student_ids, (int)$attendance_record_id, $hours_absent, $lab_attendance);
}
