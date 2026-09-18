<?php

declare(strict_types=1);

/**
 * Port of backend/src/Features/teaching_staff/@loader.ts.
 *
 * Registers the eight teaching_staff RPC handlers under their TS export
 * names (the names the frontend calls through enders-sync).
 */

use Cenusis\Rpc\Rpc;

require_once __DIR__ . '/teaching_staff_service.php';

function teachingStaffLoader(Rpc $rpc): void
{
    $rpc->add('fetchTeachers', 'fetchTeachers');
    $rpc->add('registerTeacher', 'registerTeacher');
    $rpc->add('registerAdmin', 'registerAdmin');
    $rpc->add('deleteUser', 'deleteUser');
    $rpc->add('updateUser', 'updateUser');
    $rpc->add('getProfile', 'getProfile');
    $rpc->add('changeTeacherPassword', 'changeTeacherPassword');
    $rpc->add('autocompleteTeacher', 'autocompleteTeacher');
}
