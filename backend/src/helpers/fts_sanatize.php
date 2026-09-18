<?php

declare(strict_types=1);

namespace Cenusis\Helpers;

/**
 * Port of backend/src/helpers/fts_sanatize.ts
 */
function prepareFtsPrefixQuery(string $input): string
{
    // For autocomplete, we usually want simple word prefixes.
    // Strip FTS metacharacters that break parsing or alter meaning.
    $stripped = preg_replace("/[\"'()&|!:<>*]/", ' ', $input) ?? '';
    $collapsed = preg_replace('/\s+/', ' ', $stripped) ?? '';

    return trim($collapsed) . ':*'; // add prefix wildcard
}
