<?php

declare(strict_types=1);

/**
 * Port of backend/src/Features/absence_alert_thresholds/absence_alert_thresholds.sql.ts
 * (AbsenceAlertThresholds) and absence_alert_thresholds.service.ts.
 *
 * Table helpers become plain functions with raw SQL (Cenusis\Db\Db); the RPC
 * handlers keep the exact TS export names and are registered by
 * absenceAlertThresholdsLoader() in absence_alert_thresholds_loader.php.
 */

use Cenusis\Db\Db;
use Cenusis\Rpc\Metadata;
use function Cenusis\Helpers\normalize_arabic;
use function Cenusis\Helpers\loose_validate_params;
use function Cenusis\Helpers\row_cast;

require_once __DIR__ . '/../grading_system/grading_systems_service.php';
require_once __DIR__ . '/../studying/studying_service.php';

/** pg-norm visibles for absence_alert_thresholds (SELECT list). */
const ABSENCE_ALERT_THRESHOLDS_VISIBLES = ['id', 'grading_system_id', 'alert_name', 'threshold_percent'];

// ---------------------------------------------------------------------------
// Table helpers (absence_alert_thresholds.sql.ts)
// ---------------------------------------------------------------------------

/**
 * Port of PG_Table.insert (RETURNING id -> lastInsertId).
 *
 * @param array<string, mixed> $data
 * @return array<int, array<string, mixed>>
 */
function absence_alert_thresholds_insert(array $data): array
{
    foreach (array_keys($data) as $key) {
        if (!in_array($key, ABSENCE_ALERT_THRESHOLDS_VISIBLES, true)) {
            throw new Exception('inserted rows need to have all columns in visibles');
        }
    }

    $columns = array_keys($data);
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    ['lastInsertId' => $lastInsertId] = Db::execute(
        'INSERT INTO absence_alert_thresholds (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')',
        array_values($data)
    );

    $id = array_key_exists('id', $data) ? (int)$data['id'] : (int)$lastInsertId;
    return [['id' => $id]];
}

/**
 * Port of PG_Table.update. Validates keys, returns the affected id rows.
 *
 * @param array<string, mixed> $data
 * @return array<int, array<string, mixed>>
 */
function absence_alert_thresholds_update(int $row_id, array $data): array
{
    foreach (array_keys($data) as $key) {
        if (!in_array($key, ABSENCE_ALERT_THRESHOLDS_VISIBLES, true)) {
            throw new Exception('inserted rows need to have all columns in visibles');
        }
    }

    $sets = [];
    $params = [];
    foreach ($data as $key => $value) {
        $sets[] = $key . ' = ?';
        $params[] = $value;
    }
    $params[] = $row_id;

    Db::execute(
        'UPDATE absence_alert_thresholds SET ' . implode(', ', $sets) . ' WHERE id = ?',
        $params
    );
    return [['id' => $row_id]];
}

/**
 * Port of PG_Table.delete.
 *
 * @return array<int, array<string, mixed>>
 */
function absence_alert_thresholds_delete(int $row_id): array
{
    Db::execute('DELETE FROM absence_alert_thresholds WHERE id = ?', [$row_id]);
    return [];
}

/**
 * Port of PG_Table.fetch.
 *
 * @return array<int, array<string, mixed>>
 */
function absence_alert_thresholds_fetch(int $row_id): array
{
    return array_map(
        'absence_alert_thresholds_cast_row',
        Db::query(
            'SELECT ' . implode(', ', ABSENCE_ALERT_THRESHOLDS_VISIBLES)
                . ' FROM absence_alert_thresholds WHERE id = ?',
            [$row_id]
        )
    );
}

/**
 * Port of AbsenceAlertThresholds.listAllWithGradingSystemName.
 *
 * @return array<int, array<string, mixed>>
 */
function absence_alert_thresholds_list_all_with_grading_system_name(?string $grading_system_name = null): array
{
    if ($grading_system_name !== null && trim($grading_system_name) !== '') {
        return array_map(
            'absence_alert_thresholds_cast_row',
            Db::query(
                'SELECT
                    t.id,
                    t.grading_system_id,
                    gs.name AS grading_system_name,
                    t.alert_name,
                    t.threshold_percent
                FROM absence_alert_thresholds AS t
                JOIN grading_systems AS gs ON gs.id = t.grading_system_id
                WHERE gs.name = ?
                ORDER BY t.threshold_percent ASC, t.threshold_percent ASC, t.alert_name ASC',
                [trim($grading_system_name)]
            )
        );
    }

    return array_map(
        'absence_alert_thresholds_cast_row',
        Db::query(
            'SELECT
                t.id,
                t.grading_system_id,
                gs.name AS grading_system_name,
                t.alert_name,
                t.threshold_percent
            FROM absence_alert_thresholds AS t
            JOIN grading_systems AS gs ON gs.id = t.grading_system_id
            ORDER BY gs.name ASC, t.threshold_percent ASC, t.alert_name ASC'
        )
    );
}

/**
 * Cast threshold_percent back to a real float (PostgreSQL NUMERIC wire shape).
 */
function absence_alert_thresholds_cast_row(array $row): array
{
    return row_cast($row, [], ['threshold_percent']);
}

// ---------------------------------------------------------------------------
// RPC handlers (absence_alert_thresholds.service.ts)
// ---------------------------------------------------------------------------

/**
 * Port of normalizeThresholdPercent.
 *
 * @param mixed $value
 */
function absence_alert_thresholds_normalize_percent($value): float
{
    $normalized = is_numeric($value) ? (float)$value : NAN;
    if (!is_finite($normalized) || $normalized < 0 || $normalized > 100) {
        throw new Exception('threshold_percent must be a number between 0 and 100');
    }

    return round($normalized, 2);
}

/**
 * @param array<string, mixed> $data
 */
function absence_alert_thresholds_resolve_grading_system_id(array $data): int
{
    if (is_int($data['grading_system_id'] ?? null)) {
        $gradingSystems = grading_systems_fetch($data['grading_system_id']);
        $gradingSystem = $gradingSystems[0] ?? null;
        if ($gradingSystem === null) {
            throw new Exception('grading system not found');
        }

        return (int)$gradingSystem['id'];
    }

    $name = $data['grading_system_name'] ?? null;
    if (is_string($name) && trim($name) !== '') {
        $gradingSystem = grading_systems_find_by_name(normalize_arabic(trim($name)));
        if ($gradingSystem === null) {
            throw new Exception('grading system not found');
        }

        return (int)$gradingSystem['id'];
    }

    throw new Exception('grading_system_id or grading_system_name is required');
}

function newAbsenceAlertThreshold(Metadata $metadata, array $data): int
{
    loose_validate_params($data, ['alert_name', 'threshold_percent']);

    $alertName = is_string($data['alert_name'] ?? null) ? trim($data['alert_name']) : null;
    if (!$alertName) {
        throw new Exception('alert_name is required');
    }

    $gradingSystemId = absence_alert_thresholds_resolve_grading_system_id($data);
    $thresholdPercent = absence_alert_thresholds_normalize_percent($data['threshold_percent']);

    $thresholds = absence_alert_thresholds_insert([
        'grading_system_id' => $gradingSystemId,
        'alert_name' => $alertName,
        'threshold_percent' => $thresholdPercent,
    ]);

    if ($thresholds === []) {
        throw new Exception('failed to create absence alert threshold');
    }

    return (int)($thresholds[0]['id'] ?? 0);
}

function updateAbsenceAlertThreshold(Metadata $metadata, ?int $id, array $data): int
{
    loose_validate_params($data, ['alert_name', 'threshold_percent']);

    if (!is_int($id)) {
        throw new Exception('id must be a number');
    }

    $updates = [];

    if (array_key_exists('alert_name', $data)) {
        $alertName = is_string($data['alert_name']) ? trim($data['alert_name']) : '';
        if ($alertName === '') {
            throw new Exception('alert_name cannot be empty');
        }
        $updates['alert_name'] = $alertName;
    }

    if (array_key_exists('threshold_percent', $data)) {
        $updates['threshold_percent'] = absence_alert_thresholds_normalize_percent($data['threshold_percent']);
    }

    if (array_key_exists('grading_system_id', $data) || array_key_exists('grading_system_name', $data)) {
        $updates['grading_system_id'] = absence_alert_thresholds_resolve_grading_system_id($data);
    }

    if (count($updates) === 0) {
        return $id;
    }

    absence_alert_thresholds_update($id, $updates);
    return $id;
}

function deleteAbsenceAlertThreshold(Metadata $metadata, int $id): void
{
    absence_alert_thresholds_delete($id);
}

function fetchAbsenceAlertThresholds(Metadata $metadata, ?array $filters = null): array
{
    $grading_system_name = is_array($filters) ? ($filters['grading_system_name'] ?? null) : null;

    return absence_alert_thresholds_list_all_with_grading_system_name(
        is_string($grading_system_name) ? $grading_system_name : null
    );
}

function recomputeAbsenceAlerts(Metadata $metadata): array
{
    $thresholds = absence_alert_thresholds_list_all_with_grading_system_name();
    $candidates = studying_fetch_absence_alert_candidates();

    $thresholdsBySystemId = [];

    foreach ($thresholds as $threshold) {
        $gradingSystemId = (int)$threshold['grading_system_id'];
        if (!isset($thresholdsBySystemId[$gradingSystemId])) {
            $thresholdsBySystemId[$gradingSystemId] = [];
        }

        $thresholdsBySystemId[$gradingSystemId][] = [
            'alert_name' => (string)$threshold['alert_name'],
            'threshold_percent' => (float)$threshold['threshold_percent'],
        ];
    }

    foreach ($thresholdsBySystemId as $systemId => $systemThresholds) {
        usort($systemThresholds, static fn (array $a, array $b): int => $a['threshold_percent'] <=> $b['threshold_percent']);
        $thresholdsBySystemId[$systemId] = $systemThresholds;
    }

    $updated = 0;
    foreach ($candidates as $candidate) {
        $gradingSystemId = (int)$candidate['grading_system_id'];
        $subjectTotalHours = (float)$candidate['total_hours'];
        $hoursMissed = (float)$candidate['hours_missed'];
        $currentAlertLevel = $candidate['alert_level'] === null ? null : (string)$candidate['alert_level'];

        $subjectThresholds = $thresholdsBySystemId[$gradingSystemId] ?? [];
        $absenceRatioPercent = $subjectTotalHours > 0 ? ($hoursMissed / $subjectTotalHours) * 100 : 0;

        $nextAlertLevel = null;
        foreach ($subjectThresholds as $threshold) {
            if ($absenceRatioPercent >= $threshold['threshold_percent']) {
                $nextAlertLevel = $threshold['alert_name'];
            }
        }

        if ($nextAlertLevel !== $currentAlertLevel) {
            studying_set_alert_level((int)$candidate['studying_id'], $nextAlertLevel);
            $updated += 1;
        }
    }

    return ['updated' => $updated];
}

function fetchAbsenceAlerts(Metadata $metadata, ?array $filters = null): array
{
    $degree = is_array($filters) ? ($filters['degree'] ?? null) : null;
    $class = is_array($filters) ? ($filters['class'] ?? null) : null;
    $gradingSystemName = is_array($filters) ? ($filters['grading_system_name'] ?? null) : null;

    return studying_fetch_absence_alerts(
        is_string($degree) ? $degree : null,
        is_int($class) ? $class : null,
        is_string($gradingSystemName) ? $gradingSystemName : null
    );
}
