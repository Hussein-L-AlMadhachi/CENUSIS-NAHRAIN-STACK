<?php

declare(strict_types=1);

/**
 * CLI: apply idempotent schema alterations.
 *
 * Port of backend/cli/alter.ts and the backward-compat ALTER block in
 * backend/src/Features/studying/studying.sql.ts.
 *
 * Usage (from repo root):  php backend/cli/alter.php
 *
 * MySQL 8 does NOT support "ADD COLUMN IF NOT EXISTS" (MariaDB does), so each
 * (table, column) pair is checked against information_schema.COLUMNS first.
 */

use Cenusis\Db\Db;

$backendDir = dirname(__DIR__);

if (file_exists($backendDir . '/vendor/autoload.php')) {
    require $backendDir . '/vendor/autoload.php';
} else {
    require_once $backendDir . '/src/Db/Db.php';
    require_once $backendDir . '/src/helpers/helpers.php';
}

function column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

/**
 * Backward-compat column sync for pre-existing databases.
 * Ported from the studying.sql.ts "ADD COLUMN IF NOT EXISTS" block.
 *
 * @var list<array{0:string,1:string,2:string}> $alterations [table, column, DDL fragment]
 */
$alterations = [
    ['loggedin_users', 'must_change_password', '`must_change_password` TINYINT(1) NOT NULL DEFAULT 0'],
    ['studying', 'exam_retakes', '`exam_retakes` INT DEFAULT 0'],
    ['studying', 'semester_retakes', '`semester_retakes` INT DEFAULT 0'],
    ['studying', 'is_attending_required', '`is_attending_required` TINYINT(1) DEFAULT 1'],
    ['studying', 'grade_fields', "`grade_fields` JSON NOT NULL DEFAULT ('[]')"],
    ['studying', 'alert_level', '`alert_level` VARCHAR(150) DEFAULT NULL'],
];

$pdo = Db::pdo();

echo "Applying schema alterations...\n";

foreach ($alterations as [$table, $column, $definition]) {
    if (column_exists($pdo, $table, $column)) {
        echo "  [SKIP]  {$table}.{$column} already exists\n";
        continue;
    }
    $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN {$definition}");
    echo "  [ADD]   {$table}.{$column}\n";
}

echo "Schema alterations applied successfully\n";
