<?php

declare(strict_types=1);

// IDOR / ownership regression suite: teachers must only be able to touch
// grades, enrollments, attendance and absences for subjects they are
// whitelisted on (main teacher or lab teacher). Admins are unrestricted.
//
// Run with DB env vars:
//   DB_HOST=127.0.0.1 DB_USER=root DB_PASSWORD=... DB_NAME=cenusis_ops \
//   JWT_SECRET=test-secret-key php backend/test/ownership_idor_test.php

require __DIR__ . '/../src/helpers/helpers.php';
spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'Cenusis\\')) {
        return;
    }
    $file = __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, strlen('Cenusis\\'))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

use Cenusis\Db\Db;
use Cenusis\Rpc\Metadata;

require_once __DIR__ . '/../src/Features/teaching_staff/teaching_staff_service.php';
require_once __DIR__ . '/../src/Features/grading_system/grading_systems_service.php';
require_once __DIR__ . '/../src/Features/subjects/subjects_service.php';
require_once __DIR__ . '/../src/Features/studying/studying_service.php';
require_once __DIR__ . '/../src/Features/students/students_service.php';
require_once __DIR__ . '/../src/Features/attendance_record/attendance_record_service.php';
require_once __DIR__ . '/../src/Features/absented/absented_service.php';

$failures = 0;

function check(string $label, bool $ok): void
{
    global $failures;
    echo ($ok ? '[OK]  ' : '[FAIL] ') . $label . "\n";
    if (!$ok) {
        $failures++;
    }
}

function expect_error_with(callable $fn, string $needle, string $label): void
{
    try {
        $fn();
        check($label . ' (threw)', false);
    } catch (Throwable $e) {
        check($label . ' [' . $e->getMessage() . ']', str_contains($e->getMessage(), $needle));
    }
}

if (!in_array('mysql', PDO::getAvailableDrivers(), true)) {
    echo "pdo_mysql not available, skipping IDOR suite.\n";
    exit(0);
}

putenv('JWT_SECRET=' . (getenv('JWT_SECRET') ?: 'test-secret-key'));

Db::pdo();

// ---------------------------------------------------------------------------
// seed: unique-per-run accounts so the suite is re-runnable
// ---------------------------------------------------------------------------
$tag = 'idor' . getmypid() . random_int(1000, 9999);
$admin = new Metadata(['user_id' => 0, 'role' => 'superadmin']);

$ownerTsId = registerTeacher($admin, ['teacher_name' => "مالكالمادة$tag", 'password' => 'owner12345']);
$intruderTsId = registerTeacher($admin, ['teacher_name' => "متحايل $tag", 'password' => 'hack123456']);

$ownerTs = teaching_staff_fetch((int)$ownerTsId)[0];
$intruderTs = teaching_staff_fetch((int)$intruderTsId)[0];
$owner = new Metadata(['user_id' => (int)$ownerTs['login_credentials'], 'role' => 'teacher']);
$intruder = new Metadata(['user_id' => (int)$intruderTs['login_credentials'], 'role' => 'teacher']);

// grading system + subject owned by teacher A
newGradingSystem($admin, [
    'name' => "نظام $tag",
    'fields' => [['field_name' => 'امتحان', 'min_grade' => 0, 'max_grade' => 100]],
]);
$subjectId = newSubject($admin, [
    'subject_name' => "مادة$tag",
    'grading_system_name' => "نظام $tag",
    'degree' => 'ماجستير',
    'class' => 4,
    'hours_weekly' => 10,
    'semester' => 1,
    'teacher_name' => "مالكالمادة$tag",
]);

// a second subject where B is the LAB teacher (positive whitelist case)
$labSubjectId = newSubject($admin, [
    'subject_name' => "مادةمختبر$tag",
    'grading_system_name' => "نظام $tag",
    'degree' => 'ماجستير',
    'class' => 4,
    'hours_weekly' => 10,
    'semester' => 1,
    'teacher_name' => "مالكالمادة$tag",
]);
// lab fields go through updateSubject (newSubject's key list excludes them)
updateSubject($admin, (int)$labSubjectId, [
    'subject_name' => "مادةمختبر$tag",
    'grading_system_name' => "نظام $tag",
    'degree' => 'ماجستير',
    'class' => 4,
    'hours_weekly' => 10,
    'total_hours' => 120,
    'is_attending_required' => false,
    'teacher_name' => "مالكالمادة$tag",
    'semester' => 1,
    'has_lab' => true,
    'lab_teacher_name' => "متحايل $tag",
    'max_lab_grade' => 20,
    'lab_grade_field' => 'امتحان',
    'lab_weekly_hours' => 2,
]);

// one student enrolled in the owner's subject
$studentId = newStudent($admin, [
    'student_name' => "طالب $tag",
    'degree' => 'ماجستير',
    'class' => 4,
]);
newEnrollment($admin, [
    'teacher_id' => (int)$ownerTs['id'],
    'student_id' => (int)$studentId,
    'subject_id' => (int)$subjectId,
    'studying_year' => 2026,
    'hours_missed' => 0,
]);
$enrollmentId = null;
foreach (studying_find_by_subject((int)$subjectId) as $r) {
    if ((int)$r['student'] === (int)$studentId) {
        $enrollmentId = (int)$r['id'];
    }
}
if ($enrollmentId === null) {
    fwrite(STDERR, "seed failed: enrollment not found\n");
    exit(1);
}

$PERM = 'ليس لديك صلاحية على هذه المادة';

// ---------------------------------------------------------------------------
// 1. intruder teacher vs the owner's subject
// ---------------------------------------------------------------------------
expect_error_with(
    static fn () => fetchEnrollmentsForSubject($intruder, (int)$subjectId),
    $PERM,
    'intruder fetchEnrollmentsForSubject denied'
);

expect_error_with(
    static fn () => fetchSingleEnrollment($intruder, $enrollmentId),
    $PERM,
    'intruder fetchSingleEnrollment denied'
);

expect_error_with(
    static fn () => updateEnrollment($intruder, $enrollmentId, [
        'teacher_id' => (int)$intruderTs['id'], 'student_id' => (int)$studentId,
        'subject_id' => (int)$subjectId, 'studying_year' => 2026, 'hours_missed' => 999,
    ]),
    $PERM,
    'intruder updateEnrollment denied'
);

expect_error_with(
    static fn () => deleteEnrollment($intruder, $enrollmentId),
    $PERM,
    'intruder deleteEnrollment denied'
);

expect_error_with(
    static fn () => newEnrollment($intruder, [
        'teacher_id' => (int)$ownerTs['id'], 'student_id' => (int)$studentId,
        'subject_id' => (int)$subjectId, 'studying_year' => 2026, 'hours_missed' => 0,
    ]),
    $PERM,
    'intruder newEnrollment (spoofed teacher_id) denied'
);

// ---------------------------------------------------------------------------
// 2. owner teacher CAN manage their own subject's enrollment
// ---------------------------------------------------------------------------
try {
    $newId = (int)studying_find_by_subject((int)$subjectId)[0]['id'];
    updateEnrollment($owner, $enrollmentId, [
        'teacher_id' => (int)$ownerTs['id'], 'student_id' => (int)$studentId,
        'subject_id' => (int)$subjectId, 'studying_year' => 2026, 'hours_missed' => 3,
    ]);
    $after = studying_fetch($enrollmentId)[0];
    check('owner updateEnrollment allowed (hours_missed=3)', (int)$after['hours_missed'] === 3);
} catch (Throwable $e) {
    check('owner updateEnrollment allowed [' . $e->getMessage() . ']', false);
}

// teacher cannot re-point an enrollment at another teacher/subject
try {
    updateEnrollment($owner, $enrollmentId, [
        'teacher_id' => (int)$intruderTs['id'], 'student_id' => (int)$studentId,
        'subject_id' => (int)$labSubjectId, 'studying_year' => 2026, 'hours_missed' => 0,
    ]);
    $after = studying_fetch($enrollmentId)[0];
    check('teacher cannot re-point enrollment to another teacher',
        (int)$after['teacher'] === (int)$ownerTs['id'] && (int)$after['subject'] === (int)$subjectId);
} catch (Throwable $e) {
    check('teacher cannot re-point enrollment to another teacher [' . $e->getMessage() . ']', false);
}

// ---------------------------------------------------------------------------
// 3. forced teacher ownership on newEnrollment (spoofed teacher_id ignored)
// ---------------------------------------------------------------------------
try {
    // drop leftovers from earlier crashed runs (student+subject unique pair)
    Db::execute('DELETE FROM studying WHERE subject = ? AND student = ?', [(int)$labSubjectId, (int)$studentId]);
    newEnrollment($owner, [
        'teacher_id' => (int)$intruderTs['id'], // spoofed!
        'student_id' => (int)$studentId,
        'subject_id' => (int)$labSubjectId,
        'studying_year' => 2026,
        'hours_missed' => 0,
    ]);
    $row = null;
    foreach (studying_find_by_subject((int)$labSubjectId) as $r) {
        if ((int)$r['student'] === (int)$studentId) {
            $row = $r;
        }
    }
    check('newEnrollment forces the caller as teacher (spoofed id ignored)',
        $row !== null && (int)$row['teacher'] === (int)$ownerTs['id']);
    if ($row !== null) {
        studying_delete((int)$row['id']);
    }
} catch (Throwable $e) {
    check('newEnrollment forces the caller as teacher [' . $e->getMessage() . ']', false);
}

// ---------------------------------------------------------------------------
// 4. lab teacher IS whitelisted (positive)
// ---------------------------------------------------------------------------
try {
    fetchEnrollmentsForSubject($intruder, (int)$labSubjectId);
    check('lab teacher whitelisted on subject (read)', true);
} catch (Throwable $e) {
    check('lab teacher whitelisted on subject (read) [' . $e->getMessage() . ']', false);
}

// ---------------------------------------------------------------------------
// 4b. absence / attendance IDOR through the record chain
// ---------------------------------------------------------------------------
try {
    $recordId = (int)createDailyAttendanceRecord($owner, (int)$subjectId, '2026-09-16', false)[0]['id'];
    check('owner createDailyAttendanceRecord', $recordId > 0);

    expect_error_with(
        static fn () => markStudentAbsent($intruder, ['attendance_record_id' => $recordId, 'student_id' => (int)$studentId, 'hours_absent' => 5], false),
        $PERM,
        'intruder markStudentAbsent denied'
    );
    expect_error_with(
        static fn () => markStudentAbsentBulk($intruder, ['attendance_record_id' => $recordId, 'student_ids' => [(int)$studentId], 'hours_absent' => 9], false),
        $PERM,
        'intruder markStudentAbsentBulk denied'
    );
    expect_error_with(
        static fn () => fetchAbsentStudents($intruder, $recordId),
        $PERM,
        'intruder fetchAbsentStudents denied'
    );

    // owner creates a real absence, then the intruder must not be able to
    // update or remove it through the absented id
    markStudentAbsent($owner, ['attendance_record_id' => $recordId, 'student_id' => (int)$studentId, 'hours_absent' => 5], false);
    $absentedId = (int)Db::first(
        'SELECT id FROM absented WHERE attendance_record = ? AND student = ?',
        [$recordId, (int)$studentId]
    )['id'];

    expect_error_with(
        static fn () => updateAbsence($intruder, ['absented_id' => $absentedId, 'hours_absent' => 50]),
        $PERM,
        'intruder updateAbsence denied'
    );
    expect_error_with(
        static fn () => removeAbsence($intruder, $absentedId),
        $PERM,
        'intruder removeAbsence denied'
    );

    // owner can still manage it
    updateAbsence($owner, ['absented_id' => $absentedId, 'hours_absent' => 7]);
    $row = Db::first('SELECT hours_absent FROM absented WHERE id = ?', [$absentedId]);
    check('owner updateAbsence allowed', (int)$row['hours_absent'] === 7);
    removeAbsence($owner, $absentedId);
    $row = Db::first('SELECT id FROM absented WHERE id = ?', [$absentedId]);
    check('owner removeAbsence allowed', $row === null);

    // attendance record read leak
    expect_error_with(
        static fn () => fetchAttendanceRecordWithSubject($intruder, $recordId),
        'ليس لديك صلاحية',
        'intruder fetchAttendanceRecordWithSubject denied'
    );
} catch (Throwable $e) {
    check('attendance/absence IDOR block [' . $e->getMessage() . ']', false);
}

// ---------------------------------------------------------------------------
// 5. admins are unrestricted (positive)
// ---------------------------------------------------------------------------
try {
    Db::execute('DELETE FROM studying WHERE subject = ? AND student = ?', [(int)$subjectId, (int)$studentId]);
    newEnrollment($admin, [
        'teacher_id' => (int)$intruderTs['id'], 'student_id' => (int)$studentId,
        'subject_id' => (int)$subjectId, 'studying_year' => 2025, 'hours_missed' => 0,
    ]);
    $adminEnrollment = null;
    foreach (studying_find_by_subject((int)$subjectId) as $r) {
        if ((int)$r['studying_year'] === 2025) {
            $adminEnrollment = $r;
        }
    }
    check('admin newEnrollment unrestricted (spoofed teacher_id respected)',
        $adminEnrollment !== null && (int)$adminEnrollment['teacher'] === (int)$intruderTs['id']);
    if ($adminEnrollment !== null) {
        studying_delete((int)$adminEnrollment['id']);
    }
} catch (Throwable $e) {
    check('admin unrestricted [' . $e->getMessage() . ']', false);
}

// ---------------------------------------------------------------------------
// cleanup
// ---------------------------------------------------------------------------
try {
    grading_systems_delete((int)Db::first('SELECT id FROM grading_systems WHERE name = ?', ["نظام $tag"])['id']);
    Db::execute('DELETE FROM teaching_staff WHERE id IN (?, ?)', [(int)$ownerTsId, (int)$intruderTsId]);
    Db::execute('DELETE FROM loggedin_users WHERE id IN (?, ?)',
        [(int)$ownerTs['login_credentials'], (int)$intruderTs['login_credentials']]);
    Db::execute('DELETE FROM students WHERE id = ?', [(int)$studentId]);
} catch (Throwable $e) {
    // best effort
}

echo $failures === 0 ? "\nAll IDOR tests passed.\n" : "\n{$failures} IDOR test(s) failed.\n";
exit($failures === 0 ? 0 : 1);
