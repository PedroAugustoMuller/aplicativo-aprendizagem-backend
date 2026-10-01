<?php

declare(strict_types=1);

namespace App\Shared\Domain\Contract;

/**
 * Lives in Shared because both Identity (who owns classrooms and teaching/enrolment)
 * and other modules that need to know what a user teaches or is enrolled in (Quiz,
 * Scoring) need it, and a module never imports another module's internals. Only
 * active classrooms count.
 */
interface TeachingAssignments
{
    /** @return list<string> */
    public function subjectIdsTaughtBy(string $userId): array;

    /** @return list<string> */
    public function subjectIdsEnrolledBy(string $userId): array;
}
