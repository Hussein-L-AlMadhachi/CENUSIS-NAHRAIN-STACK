<?php

declare(strict_types=1);

namespace Cenusis\Helpers;

use RuntimeException;

/**
 * Port of backend/src/helpers/headers_translate.ts
 *
 * @param array<int, array<string, mixed>> $data
 * @param array<string, string> $translations
 * @return array<int, array<string, mixed>>
 */
function translateHeaders(array $data, array $translations): array
{
    $result = [];

    foreach ($data as $row) {
        $newData = [];
        foreach ($row as $key => $value) {
            if (!array_key_exists($key, $translations)) {
                throw new RuntimeException('unexpected header ' . $key);
            }
            $newData[$translations[$key]] = $value;
        }
        $result[] = $newData;
    }

    return $result;
}
