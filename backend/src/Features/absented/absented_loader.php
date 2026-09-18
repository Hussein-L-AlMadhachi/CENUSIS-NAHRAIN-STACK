<?php

declare(strict_types=1);

/**
 * Port of backend/src/Features/absented/@loader.ts.
 */

use Cenusis\Rpc\Rpc;

require_once __DIR__ . '/absented_service.php';

function absentedLoader(Rpc $rpc): void
{
    $rpc->add('removeAbsence', 'removeAbsence');
    $rpc->add('updateAbsence', 'updateAbsence');
    $rpc->add('markStudentAbsent', 'markStudentAbsent');
    $rpc->add('markStudentAbsentBulk', 'markStudentAbsentBulk');
    $rpc->add('fetchAbsentStudents', 'fetchAbsentStudents');
}
