<?php

declare(strict_types=1);

/**
 * Port of backend/src/Features/attendance_record/@loader.ts.
 */

use Cenusis\Rpc\Rpc;

require_once __DIR__ . '/attendance_record_service.php';

function attendanceRecordLoader(Rpc $rpc): void
{
    $rpc->add('fetchDailyAttendanceRecordsForTheSubject', 'fetchDailyAttendanceRecordsForTheSubject');
    $rpc->add('createDailyAttendanceRecord', 'createDailyAttendanceRecord');
    $rpc->add('fetchDailyLabAttendanceRecordsForTheSubject', 'fetchDailyLabAttendanceRecordsForTheSubject');
    $rpc->add('fetchAttendanceRecordWithSubject', 'fetchAttendanceRecordWithSubject');
}
