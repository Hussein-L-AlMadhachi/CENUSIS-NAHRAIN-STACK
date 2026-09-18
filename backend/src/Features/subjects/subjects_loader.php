<?php

declare(strict_types=1);

/**
 * Port of backend/src/Features/subjects/@loader.ts.
 */

use Cenusis\Rpc\Rpc;

require_once __DIR__ . '/subjects_service.php';

function subjectsLoader(Rpc $rpc): void
{
    $rpc->add('newSubject', 'newSubject');
    $rpc->add('updateSubject', 'updateSubject');
    $rpc->add('deleteSubject', 'deleteSubject');
    $rpc->add('fetchSingleSubject', 'fetchSingleSubject');
    $rpc->add('fetchSubjects', 'fetchSubjects');
    $rpc->add('filterSubjectsByClassDegree', 'filterSubjectsByClassDegree');
    $rpc->add('filterSubjectsByDegree', 'filterSubjectsByDegree');
    $rpc->add('autocompleteSubject', 'autocompleteSubject');
    $rpc->add('findSubjectByName', 'findSubjectByName');
    $rpc->add('fetchSubjectsByTeacher', 'fetchSubjectsByTeacher');
    $rpc->add('fetchSubjectsByLabTeacher', 'fetchSubjectsByLabTeacher');
    // NOTE: TS exports autocompleteStudentsBySubject but its loader never
    // registers it; ported as-is (the handler exists in subjects_service.php).
}
