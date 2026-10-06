<?php

declare(strict_types=1);

/**
 * Port of backend/src/app.ts.
 *
 * Creates the four RPC route groups with the same validators, handlers and
 * mount paths as the Express app, and mounts the students xlsx routes.
 *
 * The returned Cenusis\App handles:
 *   GET  /api/{public|admin|superadmin|teacher}/discover
 *   POST /api/{public|admin|superadmin|teacher}/call
 * plus the non-RPC xlsx import/export routes.
 */

use Cenusis\App;
use Cenusis\Auth\Auth;

require_once __DIR__ . '/helpers/helpers.php';
require_once __DIR__ . '/DI.php';
require_once __DIR__ . '/routes/students_xlsx.php';

function create_app(): App
{
    $app = new App();

    // --- RPC mounts (same shape as the TS app) ---

    $publicRPC = $app->rpc('/api/public', static fn (): array => ['success' => true]);
    $publicRPC->add(static fn ($metadata, $username, $password) => Auth::login($metadata, $username, $password), 'login');
    $publicRPC->add(static fn ($metadata) => Auth::logout($metadata), 'logout');

    $changePasswordRPC = $app->rpc('/api/change-password', Auth::generateAuthValidatorForAnyRole());
    $changePasswordRPC->add(static fn ($metadata, $newPassword) => Auth::changeSelfPassword($metadata, $newPassword), 'changeSelfPassword');

    $adminRPC = $app->rpc('/api/admin', Auth::generateAuthValidatorForRoles('admin'));
    registerAdminCoreHandlers($adminRPC);
    registerAbsenceAlertHandlers($adminRPC);
    $adminRPC->add(static fn ($metadata) => Auth::getAccountInfo($metadata), 'getAccountInfo');

    $superRPC = $app->rpc('/api/superadmin', Auth::generateAuthValidatorForRoles('superadmin'));
    registerAdminCoreHandlers($superRPC);
    registerAbsenceAlertHandlers($superRPC);
    registerTeacherAttendanceHandlers($superRPC);
    $superRPC->add(static fn ($metadata) => Auth::getAccountInfo($metadata), 'getAccountInfo');
    $superRPC->add(static fn ($metadata) => Auth::logout($metadata), 'logout');

    $teachersRPC = $app->rpc('/api/teacher', Auth::generateAuthValidatorForRoles('teacher'));
    registerAdminCoreHandlers($teachersRPC);
    registerTeacherAttendanceHandlers($teachersRPC);
    $teachersRPC->add(static fn ($metadata) => Auth::getAccountInfo($metadata), 'getAccountInfo');
    $teachersRPC->add(static fn ($metadata) => Auth::logout($metadata), 'logout');

    // non-RPC (multipart upload / xlsx download) routes
    registerStudentsXlsxRoutes($app);

    return $app;
}
