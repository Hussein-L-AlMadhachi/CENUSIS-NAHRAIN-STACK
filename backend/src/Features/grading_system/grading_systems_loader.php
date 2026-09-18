<?php

declare(strict_types=1);

/**
 * Port of backend/src/Features/grading_system/@loader.ts.
 */

use Cenusis\Rpc\Rpc;

require_once __DIR__ . '/grading_systems_service.php';

function gradingSystemsLoader(Rpc $rpc): void
{
    $rpc->add('newGradingSystem', 'newGradingSystem');
    $rpc->add('updateGradingSystem', 'updateGradingSystem');
    $rpc->add('deleteGradingSystem', 'deleteGradingSystem');
    $rpc->add('fetchSingleGradingSystem', 'fetchSingleGradingSystem');
    $rpc->add('fetchGradingSystems', 'fetchGradingSystems');
    $rpc->add('autocompleteGradingSystem', 'autocompleteGradingSystem');
    $rpc->add('findGradingSystemByName', 'findGradingSystemByName');
}
