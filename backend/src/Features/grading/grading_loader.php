<?php

declare(strict_types=1);

/**
 * Port of backend/src/Features/grading/@loader.ts.
 */

use Cenusis\Rpc\Rpc;

require_once __DIR__ . '/grading_service.php';

function gradingLoader(Rpc $rpc): void
{
    $rpc->add('fetchStudentGradeFieldsPerStudying', 'fetchStudentGradeFieldsPerStudying');
    $rpc->add('fetchStudentLabGradesPerStudying', 'fetchStudentLabGradesPerStudying');
}
