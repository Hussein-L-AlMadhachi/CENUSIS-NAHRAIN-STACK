<?php

declare(strict_types=1);

/**
 * Port of backend/src/Features/grading_system/grading_systems.sql.ts
 * (GradingSystems) and grading_systems.service.ts.
 *
 * The `fields` column is a MySQL JSON column: inserts encode arrays with
 * json_encode, selects json_decode them back, matching pg-norm's JSONB
 * round-trip on Postgres.
 */

use Cenusis\Db\Db;
use Cenusis\Rpc\Metadata;
use function Cenusis\Helpers\normalize_arabic;
use function Cenusis\Helpers\validate_params;
use function Cenusis\Helpers\loose_validate_params;

/** pg-norm visibles for grading_systems. */
const GRADING_SYSTEMS_VISIBLES = ['id', 'name', 'normalized_name', 'fields'];

// ---------------------------------------------------------------------------
// Table helpers (grading_systems.sql.ts)
// ---------------------------------------------------------------------------

/**
 * Decode the JSON `fields` column into a PHP array (postgres.js parsed
 * JSONB automatically; MySQL returns the JSON string).
 *
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function grading_systems_decode_row(array $row): array
{
    if (isset($row['fields']) && is_string($row['fields'])) {
        $decoded = json_decode($row['fields'], true);
        $row['fields'] = is_array($decoded) ? $decoded : $row['fields'];
    }

    return $row;
}

/**
 * Port of PG_Table.insert (RETURNING id -> lastInsertId).
 *
 * @param array<string, mixed> $data
 * @return array<int, array<string, mixed>>
 */
function grading_systems_insert(array $data): array
{
    foreach (array_keys($data) as $key) {
        if (!in_array($key, GRADING_SYSTEMS_VISIBLES, true)) {
            throw new Exception('inserted rows need to have all columns in visibles');
        }
    }

    if (isset($data['fields']) && !is_string($data['fields'])) {
        $data['fields'] = json_encode($data['fields'], JSON_UNESCAPED_UNICODE);
    }

    $columns = array_keys($data);
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    ['lastInsertId' => $lastInsertId] = Db::execute(
        'INSERT INTO grading_systems (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')',
        array_values($data)
    );

    $id = array_key_exists('id', $data) ? (int)$data['id'] : (int)$lastInsertId;
    return [['id' => $id]];
}

/**
 * Port of PG_Table.update (RETURNING id -> affected id).
 *
 * @param array<string, mixed> $data
 * @return array<int, array<string, mixed>>
 */
function grading_systems_update(int $row_id, array $data): array
{
    foreach (array_keys($data) as $key) {
        if (!in_array($key, GRADING_SYSTEMS_VISIBLES, true)) {
            throw new Exception('inserted rows need to have all columns in visibles');
        }
    }

    if (isset($data['fields']) && !is_string($data['fields'])) {
        $data['fields'] = json_encode($data['fields'], JSON_UNESCAPED_UNICODE);
    }

    $sets = [];
    $params = [];
    foreach ($data as $key => $value) {
        $sets[] = $key . ' = ?';
        $params[] = $value;
    }
    $params[] = $row_id;

    Db::execute('UPDATE grading_systems SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);
    return [['id' => $row_id]];
}

/**
 * Port of GradingSystems.delete override: SOFT delete (deleted_at).
 */
function grading_systems_delete(int $id): array
{
    Db::execute('UPDATE grading_systems SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?', [$id]);
    return [];
}

/**
 * Port of GradingSystems.fetch (deleted_at IS NULL).
 *
 * @return array<int, array<string, mixed>>
 */
function grading_systems_fetch(int $id): array
{
    $rows = Db::query(
        'SELECT ' . implode(', ', GRADING_SYSTEMS_VISIBLES)
            . ' FROM grading_systems WHERE id = ? AND deleted_at IS NULL',
        [$id]
    );

    return array_map(static fn (array $row): array => grading_systems_decode_row($row), $rows);
}

/**
 * Port of GradingSystems.findByName (single row or null).
 *
 * @return array<string, mixed>|null
 */
function grading_systems_find_by_name(string $normalized_name): ?array
{
    $row = Db::first(
        'SELECT ' . implode(', ', GRADING_SYSTEMS_VISIBLES)
            . ' FROM grading_systems WHERE normalized_name = ? AND deleted_at IS NULL',
        [$normalized_name]
    );

    return $row === null ? null : grading_systems_decode_row($row);
}

/**
 * Port of GradingSystems.listAll (ORDER BY name ASC).
 *
 * @return array<int, array<string, mixed>>
 */
function grading_systems_list_all(): array
{
    $rows = Db::query(
        'SELECT ' . implode(', ', GRADING_SYSTEMS_VISIBLES)
            . ' FROM grading_systems WHERE deleted_at IS NULL ORDER BY name ASC'
    );

    return array_map(static fn (array $row): array => grading_systems_decode_row($row), $rows);
}

/**
 * Port of GradingSystems.autocomplete (pg_trgm replaced by LIKE %..%).
 *
 * @return array<int, array<string, mixed>>
 */
function grading_systems_autocomplete(string $searched_name): array
{
    return Db::query(
        'SELECT name, normalized_name
         FROM grading_systems
         WHERE normalized_name LIKE ? AND deleted_at IS NULL
         ORDER BY name ASC LIMIT 10',
        ['%' . $searched_name . '%']
    );
}

// ---------------------------------------------------------------------------
// Service helpers (grading_systems.service.ts)
// ---------------------------------------------------------------------------

/**
 * Port of validateGradingSystemFields: every field must be an object with
 * integer max_grade/min_grade (max >= min) and a non-empty field_name.
 *
 * @param mixed $fields
 */
function grading_systems_validate_fields(mixed $fields): void
{
    if (!is_array($fields) || !array_is_list($fields)) {
        throw new Exception('fields must be an array');
    }

    foreach ($fields as $field) {
        if (!is_array($field)) {
            throw new Exception('each grading field must be an object');
        }

        $max_grade = $field['max_grade'] ?? null;
        $min_grade = $field['min_grade'] ?? null;
        $isInteger = static fn (mixed $value): bool => is_int($value) || (is_float($value) && floor($value) === $value);

        if (!$isInteger($max_grade) || !$isInteger($min_grade)) {
            throw new Exception('max_grade and min_grade must be integers');
        }

        $field_name = $field['field_name'] ?? null;
        if (!is_string($field_name) || trim($field_name) === '') {
            throw new Exception('field_name must be a non-empty string');
        }

        if ($min_grade > $max_grade) {
            throw new Exception('min_grade cannot be greater than max_grade');
        }
    }
}

/**
 * Port of normalizePayload: validates fields + name, returns the insertable
 * payload with the normalized name.
 *
 * @param array<string, mixed> $data
 * @return array{name: string, normalized_name: string, fields: mixed}
 */
function grading_systems_normalize_payload(array $data): array
{
    grading_systems_validate_fields($data['fields'] ?? null);

    $name = $data['name'] ?? null;
    if (!is_string($name) || trim($name) === '') {
        throw new Exception('name must be a non-empty string');
    }

    return [
        'name' => trim($name),
        'normalized_name' => normalize_arabic(trim($name)),
        'fields' => $data['fields'],
    ];
}

// ---------------------------------------------------------------------------
// RPC handlers (grading_systems.service.ts)
// ---------------------------------------------------------------------------

function newGradingSystem(Metadata $metadata, array $data)
{
    validate_params($data, ['name', 'fields']);

    $gradingSystem = grading_systems_insert(grading_systems_normalize_payload($data))[0] ?? null;

    if ($gradingSystem === null) {
        throw new Exception('grading system is already in the database');
    }

    return (int)$gradingSystem['id'];
}

function updateGradingSystem(Metadata $metadata, int $id, array $data)
{
    loose_validate_params($data, ['name', 'fields']);

    grading_systems_update($id, grading_systems_normalize_payload($data));
    return $id;
}

function deleteGradingSystem(Metadata $metadata, int $id): void
{
    grading_systems_delete($id);
}

function fetchSingleGradingSystem(Metadata $metadata, int $id)
{
    $rows = grading_systems_fetch($id);
    $result = $rows[0] ?? null;

    if ($result === null) {
        throw new Exception('no grading system found');
    }

    return $result;
}

function fetchGradingSystems(Metadata $metadata): array
{
    $result = grading_systems_list_all();

    if (!is_array($result)) {
        throw new Exception('no grading systems found');
    }

    return $result;
}

/**
 * @return array<int, string>
 */
function autocompleteGradingSystem(Metadata $metadata, string $name): array
{
    $result = grading_systems_autocomplete(normalize_arabic($name));

    if (!is_array($result)) {
        throw new Exception('no grading systems found');
    }

    $names = [];
    foreach ($result as $gradingSystem) {
        $names[] = $gradingSystem['name'];
    }

    return $names;
}

function findGradingSystemByName(Metadata $metadata, string $name)
{
    $result = grading_systems_find_by_name(normalize_arabic($name));

    if ($result === null) {
        throw new Exception('no grading system found');
    }

    return $result;
}
