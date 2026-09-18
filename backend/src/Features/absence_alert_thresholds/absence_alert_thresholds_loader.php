<?php

declare(strict_types=1);

/**
 * Port of backend/src/Features/absence_alert_thresholds/@loader.ts.
 */

use Cenusis\Rpc\Rpc;

require_once __DIR__ . '/absence_alert_thresholds_service.php';

function absenceAlertThresholdsLoader(Rpc $rpc): void
{
    $rpc->add('newAbsenceAlertThreshold', 'newAbsenceAlertThreshold');
    $rpc->add('updateAbsenceAlertThreshold', 'updateAbsenceAlertThreshold');
    $rpc->add('deleteAbsenceAlertThreshold', 'deleteAbsenceAlertThreshold');
    $rpc->add('fetchAbsenceAlertThresholds', 'fetchAbsenceAlertThresholds');
    $rpc->add('recomputeAbsenceAlerts', 'recomputeAbsenceAlerts');
    $rpc->add('fetchAbsenceAlerts', 'fetchAbsenceAlerts');
}
