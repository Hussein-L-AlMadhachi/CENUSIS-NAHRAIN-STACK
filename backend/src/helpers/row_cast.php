<?php

declare(strict_types=1);

namespace Cenusis\Helpers;

/**
 * Wire-shape parity helpers for the PostgreSQL -> MySQL migration.
 *
 * postgres.js returned native types for JSONB/BOOLEAN/NUMERIC columns, while
 * PDO + mysqlnd returns MySQL JSON columns as strings, TINYINT(1)/BIT-style
 * booleans as 0/1 integers and DECIMAL/ROUND() results as strings. The
 * frontend relies on real booleans (strict `!== true` checks) and numbers,
 * so cast them back on read.
 */

/**
 * Cast boolean (0/1) and decimal/numeric string columns of a row to
 * PHP bool / float so responses match the shapes the old PostgreSQL
 * backend returned.
 *
 * @param array<string, mixed> $row
 * @param array<int, string>   $bool_columns
 * @param array<int, string>   $float_columns
 * @return array<string, mixed>
 */
function row_cast(array $row, array $bool_columns = [], array $float_columns = []): array
{
    foreach ($bool_columns as $column) {
        if (array_key_exists($column, $row) && $row[$column] !== null) {
            $row[$column] = (bool)$row[$column];
        }
    }

    foreach ($float_columns as $column) {
        if (array_key_exists($column, $row) && $row[$column] !== null) {
            $row[$column] = (float)$row[$column];
        }
    }

    return $row;
}
