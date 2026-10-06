<?php

declare(strict_types=1);

// Header warnings are expected in CLI (the dispatcher calls header()/echo).
error_reporting(E_ALL & ~E_WARNING);

/**
 * Smoke tests for the app wiring (app.php/DI.php) and the newly ported
 * feature services (studying, grading, attendance_record, absented,
 * absence_alert_thresholds), plus the xlsx codec JS-number semantics.
 *
 * Only exercises registration + pure validation paths, no DB required.
 * Run: php backend/test/wiring_smoke.php
 */

require_once __DIR__ . '/../src/helpers/helpers.php';
require_once __DIR__ . '/../src/helpers/xlsx_codec.php';
require_once __DIR__ . '/../src/Rpc/Metadata.php';
require_once __DIR__ . '/../src/Rpc/Response.php';
require_once __DIR__ . '/../src/Rpc/Rpc.php';
require_once __DIR__ . '/../src/Db/Db.php';
require_once __DIR__ . '/../src/DI.php';
require_once __DIR__ . '/../src/Auth/Jwt.php';
require_once __DIR__ . '/../src/Auth/Auth.php';
require_once __DIR__ . '/../src/App.php';
require_once __DIR__ . '/../src/routes/students_xlsx.php';
require_once __DIR__ . '/../src/app.php';

use Cenusis\App;
use Cenusis\Rpc\Metadata;
use Cenusis\Rpc\Rpc;

$failures = 0;

function check(string $label, bool $ok): void
{
    global $failures;
    if ($ok) {
        echo "  [OK] {$label}\n";
    } else {
        $failures++;
        echo "  [FAIL] {$label}\n";
    }
}

function expect_error(callable $fn, string $needle, string $label): void
{
    try {
        $fn();
        check($label . ' (threw)', false);
    } catch (Throwable $e) {
        check($label . " [{$e->getMessage()}]", str_contains($e->getMessage(), $needle));
    }
}

// --- 1. new loader registration names (mirror of TS @loader.ts) -----------
$expectedLoaders = [
    'studyingLoader' => ['newEnrollment', 'updateEnrollment', 'deleteEnrollment', 'fetchSingleEnrollment', 'fetchEnrollmentsForSubject'],
    'gradingLoader' => ['fetchStudentGradeFieldsPerStudying', 'fetchStudentLabGradesPerStudying'],
    'attendanceRecordLoader' => ['fetchDailyAttendanceRecordsForTheSubject', 'createDailyAttendanceRecord', 'fetchDailyLabAttendanceRecordsForTheSubject', 'fetchAttendanceRecordWithSubject'],
    'absentedLoader' => ['removeAbsence', 'updateAbsence', 'markStudentAbsent', 'markStudentAbsentBulk', 'fetchAbsentStudents'],
    'absenceAlertThresholdsLoader' => ['newAbsenceAlertThreshold', 'updateAbsenceAlertThreshold', 'deleteAbsenceAlertThreshold', 'fetchAbsenceAlertThresholds', 'recomputeAbsenceAlerts', 'fetchAbsenceAlerts'],
];

foreach ($expectedLoaders as $loader => $expected) {
    $rpc = new Rpc();
    $loader($rpc);
    check("{$loader} registers all handlers", $rpc->dump() === $expected);
}

$metadata = new Metadata(['user_id' => 1, 'role' => 'admin']);
$badAuthMetadata = new Metadata(['role' => 'admin']);

// --- 2. studying ----------------------------------------------------------
expect_error(
    static fn () => newEnrollment($metadata, ['teacher_id' => 1, 'student_id' => 2, 'subject_id' => 3, 'studying_year' => 2026]),
    'invalid request: missing key hours_missed',
    'newEnrollment missing hours_missed'
);

expect_error(
    static fn () => newEnrollment($metadata, ['teacher_id' => 1, 'student_id' => 2, 'subject_id' => 3, 'studying_year' => 2026, 'hours_missed' => 0, 'bogus' => 1]),
    'invalid request: unexpected key bogus',
    'newEnrollment extra key'
);

expect_error(
    static fn () => updateEnrollment($metadata, 1, ['teacher_id' => 1, 'student_id' => 2, 'subject_id' => 3, 'studying_year' => 0, 'hours_missed' => 0]),
    'Unexpected error: studying_year cannot be anythin but a positive number',
    'updateEnrollment studying_year validation'
);

expect_error(
    static fn () => updateEnrollment($metadata, 1, ['teacher_id' => 1, 'student_id' => 2, 'subject_id' => 3, 'studying_year' => 'x', 'hours_missed' => 0]),
    'Unexpected error: studying_year cannot be anythin but a positive number',
    'updateEnrollment studying_year type'
);

// --- 3. attendance_record -------------------------------------------------
expect_error(
    static fn () => createDailyAttendanceRecord($badAuthMetadata, 1, '2026-01-01', false),
    'قم بتسجيل الدخول اولاً',
    'createDailyAttendanceRecord bad auth (arabic, no DB access)'
);

expect_error(
    static fn () => fetchDailyAttendanceRecordsForTheSubject($badAuthMetadata, 1),
    'قم بتسجيل الدخول اولاً',
    'fetchDailyAttendanceRecordsForTheSubject bad auth'
);

expect_error(
    static fn () => fetchDailyLabAttendanceRecordsForTheSubject($badAuthMetadata, 1),
    'قم بتسجيل الدخول اولاً',
    'fetchDailyLabAttendanceRecordsForTheSubject bad auth'
);

// --- 4. absented ----------------------------------------------------------
expect_error(
    static fn () => markStudentAbsent($metadata, ['attendance_record_id' => 1, 'student_id' => 'x', 'hours_absent' => 2], false),
    'student_id must be a valid number',
    'markStudentAbsent student_id type'
);

expect_error(
    static fn () => markStudentAbsent($metadata, ['attendance_record_id' => 1, 'student_id' => 1, 'hours_absent' => 'x'], false),
    'hours_absent must be a valid number',
    'markStudentAbsent hours_absent type'
);

expect_error(
    static fn () => updateAbsence($metadata, ['absented_id' => 1]),
    'invalid request: missing key hours_absent',
    'updateAbsence missing key'
);

expect_error(
    static fn () => markStudentAbsentBulk($metadata, ['attendance_record_id' => 1, 'student_ids' => [], 'hours_absent' => 1], false),
    'student_ids must be a non-empty array',
    'markStudentAbsentBulk empty ids'
);

expect_error(
    static fn () => markStudentAbsentBulk($metadata, ['attendance_record_id' => 1, 'student_ids' => ['x', 1], 'hours_absent' => 'y'], false),
    'hours_absent must be a valid number',
    'markStudentAbsentBulk hours_absent type (fires before any DB access)'
);

expect_error(
    static fn () => removeAbsence($metadata, 0),
    'Absented ID is required',
    'removeAbsence falsy id'
);

// --- 5. absence_alert_thresholds ------------------------------------------
check('normalize percent valid', absence_alert_thresholds_normalize_percent('33.335') === 33.34);
expect_error(
    static fn () => absence_alert_thresholds_normalize_percent(150),
    'threshold_percent must be a number between 0 and 100',
    'normalize percent > 100'
);
expect_error(
    static fn () => absence_alert_thresholds_normalize_percent(-1),
    'threshold_percent must be a number between 0 and 100',
    'normalize percent < 0'
);
expect_error(
    static fn () => absence_alert_thresholds_normalize_percent('abc'),
    'threshold_percent must be a number between 0 and 100',
    'normalize percent NaN'
);

expect_error(
    static fn () => newAbsenceAlertThreshold($metadata, ['alert_name' => 'a']),
    'invalid request: missing key threshold_percent',
    'newAbsenceAlertThreshold missing key'
);

expect_error(
    static fn () => absence_alert_thresholds_resolve_grading_system_id(['alert_name' => 'a']),
    'grading_system_id or grading_system_name is required',
    'resolveGradingSystemId required'
);

expect_error(
    static fn () => updateAbsenceAlertThreshold($metadata, 1, []),
    'invalid request: missing key alert_name',
    'updateAbsenceAlertThreshold loose_validate'
);

// --- 6. xlsx_number JS Number() semantics ---------------------------------
check('Number(null) === 0', xlsx_number(null) === 0.0);
check("Number('') === 0", xlsx_number('') === 0.0);
check('Number("abc") is NaN', is_nan(xlsx_number('abc')));
check('Number("5") === 5', xlsx_number('5') === 5.0);
check('Number(2.5) === 2.5', xlsx_number(2.5) === 2.5);

// --- 7. app wiring boots and dispatches discover --------------------------
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = [];
$_COOKIE = [];

$adminCore = [
    'fetchTeachers', 'registerTeacher', 'registerAdmin', 'deleteUser', 'updateUser', 'getProfile', 'changeTeacherPassword', 'autocompleteTeacher',
    'newStudent', 'updateStudent', 'deleteStudent', 'fetchStudentInfo', 'filterStudentsByClassDegree', 'filterStudentsByDegree', 'autocompleteStudent', 'findStudentByName',
    'newSubject', 'updateSubject', 'deleteSubject', 'fetchSingleSubject', 'fetchSubjects', 'filterSubjectsByClassDegree', 'filterSubjectsByDegree', 'autocompleteSubject', 'findSubjectByName', 'fetchSubjectsByTeacher', 'fetchSubjectsByLabTeacher',
    'newGradingSystem', 'updateGradingSystem', 'deleteGradingSystem', 'fetchSingleGradingSystem', 'fetchGradingSystems', 'autocompleteGradingSystem', 'findGradingSystemByName',
    'newEnrollment', 'updateEnrollment', 'deleteEnrollment', 'fetchSingleEnrollment', 'fetchEnrollmentsForSubject',
    'fetchStudentGradeFieldsPerStudying', 'fetchStudentLabGradesPerStudying',
];
$absenceAlertHandlers = ['newAbsenceAlertThreshold', 'updateAbsenceAlertThreshold', 'deleteAbsenceAlertThreshold', 'fetchAbsenceAlertThresholds', 'recomputeAbsenceAlerts', 'fetchAbsenceAlerts'];
$teacherAttendanceHandlers = [
    'fetchDailyAttendanceRecordsForTheSubject', 'createDailyAttendanceRecord',
    'fetchDailyLabAttendanceRecordsForTheSubject', 'fetchAttendanceRecordWithSubject',
    'removeAbsence', 'updateAbsence', 'markStudentAbsent', 'markStudentAbsentBulk', 'fetchAbsentStudents',
];

$discoverExpectations = [
    '/api/public/discover' => ['login', 'logout'],
    '/api/change-password/discover' => ['changeSelfPassword'],
    '/api/admin/discover' => array_merge($adminCore, $absenceAlertHandlers, ['getAccountInfo']),
    '/api/superadmin/discover' => array_merge($adminCore, $absenceAlertHandlers, $teacherAttendanceHandlers, ['getAccountInfo', 'logout']),
    '/api/teacher/discover' => array_merge($adminCore, $teacherAttendanceHandlers, ['getAccountInfo', 'logout']),
];

foreach ($discoverExpectations as $uri => $expected) {
    $_SERVER['REQUEST_URI'] = $uri;
    ob_start();
    create_app()->run();
    $body = (string)ob_get_clean();
    $decoded = json_decode($body, true);
    check("{$uri} discover list", $decoded === $expected);
}

// teacher: admin core + teacher attendance + getAccountInfo + logout
$_SERVER['REQUEST_URI'] = '/api/teacher/discover';
ob_start();
create_app()->run();
$decoded = json_decode((string)ob_get_clean(), true);
check(
    '/api/teacher/discover discover list',
    is_array($decoded)
    && in_array('fetchEnrollmentsForSubject', $decoded, true)
    && in_array('createDailyAttendanceRecord', $decoded, true)
    && in_array('getAccountInfo', $decoded, true)
    && in_array('logout', $decoded, true)
    && !in_array('changeSelfPassword', $decoded, true)
    && !in_array('recomputeAbsenceAlerts', $decoded, true)
);

// unknown route -> 404
$_SERVER['REQUEST_URI'] = '/api/nope/discover';
ob_start();
create_app()->run();
$decoded = json_decode((string)ob_get_clean(), true);
check('404 for unknown mount', $decoded === ['error' => 'Not found']);

// xlsx route unauthorized -> 401 (auth check happens before any DB access)
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/api/students/import';
$_FILES = [];
ob_start();
create_app()->run();
$decoded = json_decode((string)ob_get_clean(), true);
check('POST /api/students/import unauthorized', $decoded === ['success' => false, 'error' => 'unauthorized']);

$_SERVER['REQUEST_URI'] = '/api/grades/import/123';
$_FILES = [];
ob_start();
create_app()->run();
$decoded = json_decode((string)ob_get_clean(), true);
// NOTE: TS fetches the subject BEFORE the auth check; without a DB driver
// in this environment the fetch throws, which the route maps to:
check(
    'POST /api/grades/import/:subjectid unauthorized-or-invalid-subject',
    $decoded === ['success' => false, 'error' => 'unauthorized']
    || $decoded === ['success' => false, 'error' => 'Invalid subject id']
);

// empty param does not match the Express route -> 404 (dead-code parity)
$_SERVER['REQUEST_URI'] = '/api/grades/import/';
ob_start();
create_app()->run();
$decoded = json_decode((string)ob_get_clean(), true);
check('POST /api/grades/import/ (no id) -> 404', $decoded === ['error' => 'Not found']);

echo $failures === 0 ? "\nAll wiring smoke tests passed.\n" : "\n{$failures} test(s) FAILED.\n";
exit($failures === 0 ? 0 : 1);
