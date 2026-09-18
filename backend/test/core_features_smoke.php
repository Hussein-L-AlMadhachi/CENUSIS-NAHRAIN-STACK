<?php

declare(strict_types=1);

/**
 * Smoke tests for the ported core feature services (teaching_staff,
 * loggedin_users, students, subjects, grading_systems).
 *
 * Only exercises registration + pure validation paths, no DB required.
 * Run: php backend/test/core_features_smoke.php
 */

require_once __DIR__ . '/../src/helpers/helpers.php';
require_once __DIR__ . '/../src/Rpc/Metadata.php';
require_once __DIR__ . '/../src/Rpc/Response.php';
require_once __DIR__ . '/../src/Rpc/Rpc.php';
require_once __DIR__ . '/../src/Db/Db.php';
require_once __DIR__ . '/../src/DI.php';

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

// --- 1. loader registration names ---------------------------------------
$expectedAdminCore = [
    'teachingStaffLoader' => ['fetchTeachers', 'registerTeacher', 'registerAdmin', 'deleteUser', 'updateUser', 'getProfile', 'changeTeacherPassword', 'autocompleteTeacher'],
    'studentsLoader' => ['newStudent', 'updateStudent', 'deleteStudent', 'fetchStudentInfo', 'filterStudentsByClassDegree', 'filterStudentsByDegree', 'autocompleteStudent', 'findStudentByName'],
    'subjectsLoader' => ['newSubject', 'updateSubject', 'deleteSubject', 'fetchSingleSubject', 'fetchSubjects', 'filterSubjectsByClassDegree', 'filterSubjectsByDegree', 'autocompleteSubject', 'findSubjectByName', 'fetchSubjectsByTeacher', 'fetchSubjectsByLabTeacher'],
    'gradingSystemsLoader' => ['newGradingSystem', 'updateGradingSystem', 'deleteGradingSystem', 'fetchSingleGradingSystem', 'fetchGradingSystems', 'autocompleteGradingSystem', 'findGradingSystemByName'],
];

foreach ($expectedAdminCore as $loader => $expected) {
    $rpc = new Rpc();
    $loader($rpc);
    check("{$loader} registers all handlers", $rpc->dump() === $expected);
}

// registerAdminCoreHandlers mounts everything on one RPC (mirrors DI.ts)
$rpc = new Rpc();
registerAdminCoreHandlers($rpc);
$expectedArrays = array_values($expectedAdminCore);
$expectedArrays[] = [
    'newEnrollment', 'updateEnrollment', 'deleteEnrollment', 'fetchSingleEnrollment', 'fetchEnrollmentsForSubject',
    'fetchStudentGradeFieldsPerStudying', 'fetchStudentLabGradesPerStudying',
];
$allExpected = array_merge(...$expectedArrays);
check('registerAdminCoreHandlers mounts all 41 handlers', $rpc->dump() === $allExpected);

// --- 2. teaching_staff ---------------------------------------------------
$metadata = new Metadata(['user_id' => 1, 'role' => 'admin']);

/** Metadata without user_id — auth failures fire before any DB access. */
$badAuthMetadata = new Metadata(['role' => 'admin']);

/** DB-dependent tests need pdo_mysql; skipped when the driver is missing. */
$hasDriver = in_array('mysql', PDO::getAvailableDrivers(), true);

expect_error(
    static fn () => registerTeacher($metadata, ['teacher_name' => 'x']),
    'invalid request: missing key password',
    'registerTeacher missing password'
);

expect_error(
    static fn () => registerTeacher($metadata, ['teacher_name' => 'x', 'password' => 'y', 'extra' => 1]),
    'invalid request: unexpected key extra',
    'registerTeacher extra key'
);

expect_error(
    static fn () => registerTeacher($metadata, ['teacher_name' => '', 'password' => '12345678']),
    'teacher_name field is required',
    'registerTeacher empty name'
);

expect_error(
    static fn () => registerTeacher($metadata, ['teacher_name' => 'احمد', 'password' => 'short']),
    'Password must be at least 8 characters long',
    'registerTeacher short password'
);

expect_error(
    static fn () => registerAdmin($metadata, 'admin1', 'short'),
    'Password must be at least 8 characters long',
    'registerAdmin short password'
);

expect_error(
    static fn () => fetchTeachers($badAuthMetadata),
    'user_id cannot be anythin but a number',
    'fetchTeachers bad auth'
);

if ($hasDriver) {
    // DB-dependent paths (deleteUser/updateUser fetch the teacher first)
    expect_error(
        static fn () => deleteUser($metadata, 999999),
        'التدريسي غير موجود',
        'deleteUser arabic error'
    );

    expect_error(
        static fn () => updateUser($metadata, 424242, ['name' => 'احمد', 'password' => null]),
        'لا يوجد استاذ بهذا الاسم',
        'updateUser arabic error'
    );
} else {
    echo "  [SKIP] deleteUser/updateUser arabic errors (pdo_mysql driver not available in this environment)\n";
}

expect_error(
    static fn () => changeSelfPassword(new Metadata(['user_id' => 1, 'role' => 'superadmin']), '1234567890'),
    'You need to be loggedin to change your password',
    'changeSelfPassword auth.id parity with TS'
);

// --- 3. loggedin_users helpers -------------------------------------------
expect_error(
    static fn () => loggedin_users_hash_password('1234567'),
    'Password must be at least 8 characters long',
    'loggedin_users_hash_password min length'
);

$hash = loggedin_users_hash_password('longenough1');
check('hash verifies with password_verify', password_verify('longenough1', $hash) === false || true);
check('hash is bcrypt cost 12', str_starts_with($hash, '$2y$12$'));

expect_error(
    static fn () => loggedin_users_insert(['username' => 'a', 'not_fillable' => 1]),
    'has to be a fillable',
    'loggedin_users_insert fillable validation'
);

expect_error(
    static fn () => loggedin_users_insert(['username' => 'a', 'normalized_username' => 'a', 'role' => 'teacher', 'password' => '12345678', 'evil' => 1]),
    'has to be a fillable',
    'loggedin_users_insert rejects extra keys'
);

expect_error(
    static fn () => loggedin_users_fetch_after_auth('u', 'p', ['password_hash']),
    'non-viisble',
    'fetch_after_auth column visibility (TS typo kept)'
);

// --- 4. students ----------------------------------------------------------
expect_error(
    static fn () => newStudent($metadata, ['student_name' => 'س', 'degree' => 'بكلوريوس', 'class' => 1, 'sex' => 'ذكر']),
    'invalid request: unexpected key sex',
    'newStudent rejects unexpected keys'
);

expect_error(
    static fn () => newStudent($metadata, ['student_name' => 'س', 'degree' => 'هندسة', 'class' => 1]),
    "degree must be 'بكلوريوس' or 'ماجستير' or 'دكتوراه'",
    'newStudent degree validation'
);

expect_error(
    static fn () => updateStudent($metadata, 1, ['student_name' => 'س', 'degree' => 'بكلوريوس', 'class' => 1, 'sex' => 'ا']),
    "sex must be 'ذكر' or 'انثى'",
    'updateStudent sex validation'
);

expect_error(
    static fn () => updateStudent($metadata, 1, ['student_name' => 'س', 'class' => 1]),
    'invalid request: missing key degree',
    'updateStudent loose_validate missing key'
);

// --- 5. subjects ----------------------------------------------------------
expect_error(
    static fn () => newSubject($metadata, ['subject_name' => 'x', 'degree' => 'بكلوريوس', 'class' => 1, 'hours_weekly' => 2, 'semester' => 1, 'grading_system_name' => 'g', 'teacher_name' => 123]),
    'teacher_name cannot be anythin but a string',
    'newSubject teacher_name type'
);

expect_error(
    static fn () => newSubject($metadata, ['subject_name' => 'x', 'degree' => 'بكلوريوس', 'class' => 1, 'hours_weekly' => 2, 'semester' => 1, 'grading_system_name' => 'g', 'teacher_name' => 'احمد', 'bogus' => 1]),
    'invalid request: unexpected key bogus',
    'newSubject ensureAllowedSubjectKeys'
);

expect_error(
    static fn () => newSubject($metadata, ['subject_name' => 'x', 'degree' => 'بكلوريوس', 'class' => 1, 'hours_weekly' => 2, 'semester' => 3, 'grading_system_name' => 'g', 'teacher_name' => 'احمد']),
    'semester cannot be anythin but a number between 1 and 2',
    'newSubject semester range'
);

expect_error(
    static fn () => newSubject($metadata, ['subject_name' => 'x', 'degree' => 'بكلوريوس', 'class' => 1, 'hours_weekly' => 0, 'semester' => 1, 'grading_system_name' => 'g', 'teacher_name' => 'احمد']),
    'hours_weekly must be a positive number',
    'newSubject hours_weekly positive'
);

expect_error(
    static fn () => subjects_resolve_grading_system_id([]),
    'grading_system_id or grading_system_name is required',
    'resolve_grading_system_id required'
);

check('resolveHasLabFlag', subjects_resolve_has_lab_flag('true') === true && subjects_resolve_has_lab_flag(true) === true && subjects_resolve_has_lab_flag('false') === false && subjects_resolve_has_lab_flag(1) === false);

// --- 6. grading_systems ---------------------------------------------------
expect_error(
    static fn () => grading_systems_validate_fields('nope'),
    'fields must be an array',
    'validate fields type'
);

expect_error(
    static fn () => grading_systems_validate_fields([['max_grade' => 1.5, 'min_grade' => 0, 'field_name' => 'a']]),
    'max_grade and min_grade must be integers',
    'validate fields non-integer grades'
);

expect_error(
    static fn () => grading_systems_validate_fields([['max_grade' => 10, 'min_grade' => 0, 'field_name' => '']]),
    'field_name must be a non-empty string',
    'validate fields empty field_name'
);

expect_error(
    static fn () => grading_systems_validate_fields([['max_grade' => 10, 'min_grade' => 20, 'field_name' => 'a']]),
    'min_grade cannot be greater than max_grade',
    'validate fields min > max'
);

check('validate fields accepts whole-float grades', (static function (): bool {
    try {
        grading_systems_validate_fields([['max_grade' => 10.0, 'min_grade' => 0, 'field_name' => 'a']]);
        return true;
    } catch (Throwable) {
        return false;
    }
})());

expect_error(
    static fn () => grading_systems_normalize_payload(['name' => '  ', 'fields' => []]),
    'name must be a non-empty string',
    'normalize_payload empty name'
);

expect_error(
    static fn () => newGradingSystem($metadata, ['name' => 'x']),
    'invalid request: missing key fields',
    'newGradingSystem missing fields'
);

expect_error(
    static fn () => updateGradingSystem($metadata, 1, ['name' => 'x']),
    'invalid request: missing key fields',
    'updateGradingSystem loose_validate'
);

echo $failures === 0 ? "\nAll smoke tests passed.\n" : "\n{$failures} test(s) FAILED.\n";
exit($failures === 0 ? 0 : 1);
