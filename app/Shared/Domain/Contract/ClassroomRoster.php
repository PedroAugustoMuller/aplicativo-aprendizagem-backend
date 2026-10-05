<?php

declare(strict_types=1);

namespace App\Shared\Domain\Contract;

/**
 * Lives in Shared because Identity owns classrooms while Quiz shows a classroom's
 * progress to its teachers, and a module never imports another module's internals.
 */
interface ClassroomRoster
{
    /** Null when no classroom has this id. Deactivated classrooms are returned (active: false). */
    public function find(string $classroomId): ?RosterClassroom;

    /**
     * Every classroom of the subject, active or not, by name; empty for an unknown subject.
     *
     * @return list<RosterClassroom>
     */
    public function forSubject(string $subjectId): array;
}
