<?php

declare(strict_types=1);

/**
 * Port of backend/src/Features/students/@loader.ts.
 */

use Cenusis\Rpc\Rpc;

require_once __DIR__ . '/students_service.php';

function studentsLoader(Rpc $rpc): void
{
    $rpc->add('newStudent', 'newStudent');
    $rpc->add('updateStudent', 'updateStudent');
    $rpc->add('deleteStudent', 'deleteStudent');
    $rpc->add('fetchStudentInfo', 'fetchStudentInfo');
    $rpc->add('filterStudentsByClassDegree', 'filterStudentsByClassDegree');
    $rpc->add('filterStudentsByDegree', 'filterStudentsByDegree');
    $rpc->add('autocompleteStudent', 'autocompleteStudent');
    $rpc->add('findStudentByName', 'findStudentByName');
}
