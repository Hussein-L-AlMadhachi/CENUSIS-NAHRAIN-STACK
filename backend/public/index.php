<?php

declare(strict_types=1);

// Front controller, mirrors backend/src/app.ts (Node/Express version).
//
// The application wiring (4 RPC route groups + xlsx routes) lives in
// src/app.php; this file only boots it.

// composer autoloader, with a fallback PSR-4 loader for local dev
// environments without composer installed.
if (is_file(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(static function (string $class): void {
        if (!str_starts_with($class, 'Cenusis\\')) {
            return;
        }
        $file = __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, strlen('Cenusis\\'))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    });
}

require __DIR__ . '/../src/app.php';

$app = create_app();
$app->run();
