<?php

declare(strict_types=1);

namespace Cenusis\Helpers;

/**
 * Port of backend/src/helpers/validate_params.ts
 *
 * @param array<string, mixed> $data
 * @param array<int, string> $valid_params
 */
function validate_params(array $data, array $valid_params): void
{
    // 1. No extra keys
    foreach (array_keys($data) as $key) {
        if (!in_array($key, $valid_params, true)) {
            throw new \RuntimeException('invalid request: unexpected key ' . $key);
        }
    }

    // 2. All required keys present
    foreach ($valid_params as $key) {
        if (!array_key_exists($key, $data)) {
            throw new \RuntimeException('invalid request: missing key ' . $key);
        }
    }
}

/**
 * Only checks that all required keys are present (extra keys allowed).
 *
 * @param array<string, mixed> $data
 * @param array<int, string> $valid_params
 */
function loose_validate_params(array $data, array $valid_params): void
{
    // All required keys present
    foreach ($valid_params as $key) {
        if (!array_key_exists($key, $data)) {
            throw new \RuntimeException('invalid request: missing key ' . $key);
        }
    }
}
