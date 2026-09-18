<?php

declare(strict_types=1);

namespace Cenusis\Helpers;

/**
 * Port of backend/src/helpers/normalize_arabic.ts
 */

const ARABIC_TRANSALTION = [
    'ا' => 'ا',
    'أ' => 'ا',
    'إ' => 'ا',
    'آ' => 'ا',
    'ى' => 'ا',
    'ب' => 'ب',
    'ت' => 'ت',
    'ث' => 'ث',
    'ج' => 'ج',
    'ح' => 'ح',
    'خ' => 'خ',
    'د' => 'د',
    'ذ' => 'ذ',
    'ر' => 'ر',
    'ز' => 'ز',
    'س' => 'س',
    'ش' => 'ش',
    'ص' => 'س',
    'ض' => 'ض',
    'ط' => 'ط',
    'ظ' => 'ظ',
    'ع' => 'ع',
    'غ' => 'غ',
    'ف' => 'ف',
    'ق' => 'ق',
    'ك' => 'ك',
    'ل' => 'ل',
    'م' => 'م',
    'ن' => 'ن',
    'ه' => 'ه',
    'و' => 'و',
    'ؤ' => 'و',
    'ي' => 'ي',
    'ئ' => 'ي',
    'ء' => 'ا',
    'ة' => 'ه',
];

function normalize_arabic(mixed $text): string
{
    $safeText = (string)($text ?? '');

    $out = '';
    foreach (mb_str_split($safeText) as $letter) {
        $out .= ARABIC_TRANSALTION[$letter] ?? $letter;
    }
    return $out;
}
