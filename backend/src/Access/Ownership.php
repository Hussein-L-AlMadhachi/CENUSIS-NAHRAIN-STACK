<?php

declare(strict_types=1);

namespace Cenusis\Access;

use Cenusis\Rpc\Metadata;

require_once __DIR__ . '/../Features/teaching_staff/teaching_staff_service.php';
require_once __DIR__ . '/../Features/subjects/subjects_service.php';

/**
 * Subject ownership / IDOR guard shared by the grade-import routes and the
 * enrollment, attendance and absence RPC handlers.
 *
 * Admins and superadmins are unrestricted. Teachers may only touch subjects
 * they are whitelisted on: as the main teacher (subjects.teacher) or as the
 * lab teacher (subjects.lab_teacher).
 */

/**
 * The teaching_staff row for a logged-in user id, or null.
 *
 * @return array<string, mixed>|null
 */
function teacher_for_user(int $user_id): ?array
{
    return teaching_staff_fetch_by_user_id($user_id);
}

/**
 * Whether the given (already resolved) subject row is whitelisted for the
 * given teaching_staff id (main teacher or lab teacher).
 *
 * @param array<string, mixed> $subject subjects row
 */
function subject_owned_by_teacher(array $subject, int $teacher_id): bool
{
    return (int)$subject['teacher'] === $teacher_id
        || (int)($subject['lab_teacher'] ?? 0) === $teacher_id;
}

/**
 * Assert that the caller may access the subject. Admins/superadmins pass;
 * teachers must be whitelisted on the subject (main or lab teacher).
 *
 * @return array<string, mixed> the fetched subject row
 */
function assert_subject_access(Metadata $metadata, int $subject_id): array
{
    if (($metadata->auth['role'] ?? null) !== 'teacher') {
        $subjects = subjects_fetch($subject_id);
        if (($subjects[0] ?? null) === null || !empty($subjects[0]['deleted_at'])) {
            throw new \Exception('Subject not found');
        }
        return $subjects[0];
    }

    $uid = $metadata->auth['user_id'] ?? null;
    if (!is_int($uid)) {
        throw new \Exception('قم بتسجيل الدخول اولاً');
    }

    $teacher = teacher_for_user($uid);
    if ($teacher === null) {
        throw new \Exception('teacher not authorized');
    }

    $subjects = subjects_fetch($subject_id);
    $subject = $subjects[0] ?? null;
    if ($subject === null || !empty($subject['deleted_at'])) {
        throw new \Exception('Subject not found');
    }

    if (!subject_owned_by_teacher($subject, (int)$teacher['id'])) {
        throw new \Exception('ليس لديك صلاحية على هذه المادة');
    }

    return $subject;
}
