<?php

declare(strict_types=1);

/**
 * Port of backend/src/Features/grading/grading.service.ts.
 *
 * Thin RPC wrappers over the studying table helpers.
 */

use Cenusis\Rpc\Metadata;

require_once __DIR__ . '/../studying/studying_service.php';

function fetchStudentGradeFieldsPerStudying(Metadata $metadata, int $subject_id): array
{
    // NOTE: the TS guard `if (!gradeFields) throw` is unreachable for an
    // empty list (empty arrays are truthy in JS), so an empty result is
    // returned as-is, mirroring the TS backend.
    return studying_get_grade_fields($subject_id);
}

function fetchStudentLabGradesPerStudying(Metadata $metadata, int $subject_id): array
{
    return studying_get_lab_grades($subject_id);
}
