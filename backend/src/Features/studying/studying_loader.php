<?php

declare(strict_types=1);

/**
 * Port of backend/src/Features/studying/@loader.ts.
 */

use Cenusis\Rpc\Rpc;

require_once __DIR__ . '/studying_service.php';

function studyingLoader(Rpc $rpc): void
{
    $rpc->add('newEnrollment', 'newEnrollment');
    $rpc->add('updateEnrollment', 'updateEnrollment');
    $rpc->add('deleteEnrollment', 'deleteEnrollment');
    $rpc->add('fetchSingleEnrollment', 'fetchSingleEnrollment');
    $rpc->add('fetchEnrollmentsForSubject', 'fetchEnrollmentsForSubject');
}
