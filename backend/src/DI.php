<?php

declare(strict_types=1);

/**
 * Port of backend/src/DI.ts.
 */

use Cenusis\Rpc\Rpc;

require_once __DIR__ . '/Features/teaching_staff/teaching_staff_loader.php';
require_once __DIR__ . '/Features/students/students_loader.php';
require_once __DIR__ . '/Features/subjects/subjects_loader.php';
require_once __DIR__ . '/Features/grading_system/grading_systems_loader.php';
require_once __DIR__ . '/Features/studying/studying_loader.php';
require_once __DIR__ . '/Features/grading/grading_loader.php';
require_once __DIR__ . '/Features/absented/absented_loader.php';
require_once __DIR__ . '/Features/attendance_record/attendance_record_loader.php';
require_once __DIR__ . '/Features/absence_alert_thresholds/absence_alert_thresholds_loader.php';

function registerAdminCoreHandlers(Rpc $rpc): void
{
    teachingStaffLoader($rpc);
    studentsLoader($rpc);
    subjectsLoader($rpc);
    gradingSystemsLoader($rpc);
    studyingLoader($rpc);
    gradingLoader($rpc);
}

function registerTeacherAttendanceHandlers(Rpc $rpc): void
{
    attendanceRecordLoader($rpc);
    absentedLoader($rpc);
}

function registerAbsenceAlertHandlers(Rpc $rpc): void
{
    absenceAlertThresholdsLoader($rpc);
}
