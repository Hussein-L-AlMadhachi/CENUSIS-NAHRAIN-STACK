<?php

declare(strict_types=1);

// Aggregator for all helper functions (loaded via composer "files" autoload
// or explicitly by public/index.php).

require_once __DIR__ . '/normalize_arabic.php';
require_once __DIR__ . '/validate_params.php';
require_once __DIR__ . '/fts_sanatize.php';
require_once __DIR__ . '/headers_translate.php';
require_once __DIR__ . '/row_cast.php';
