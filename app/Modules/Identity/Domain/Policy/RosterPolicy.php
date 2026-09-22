<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Policy;

use App\Modules\Identity\Domain\Entity\Classroom;
use App\Modules\Identity\Domain\Repository\ClassroomRepository;
use App\Modules\Identity\Domain\ValueObject\UserId;
use App\Shared\Domain\Auth\Actor;
use App\Shared\Domain\Auth\Role;

/** Who may manage a classroom's roster: enrol, unenrol, or act on one of its students. */
final readonly class RosterPolicy
{
    public function __construct(private ClassroomRepository $classrooms) {}

    public function canManageClassroom(Actor $actor, Classroom $classroom): bool
    {
        return $actor->isAdmin()
            || ($actor->role === Role::Teacher && $classroom->isTaughtBy(new UserId($actor->userId)));
    }

    /** A teacher manages a student if they teach ANY classroom the student is enrolled in. */
    public function canManageStudent(Actor $actor, UserId $studentId): bool
    {
        if ($actor->isAdmin()) {
            return true;
        }

        if ($actor->role !== Role::Teacher) {
            return false;
        }

        foreach ($this->classrooms->findByStudent($studentId) as $classroom) {
            if ($classroom->isTaughtBy(new UserId($actor->userId))) {
                return true;
            }
        }

        return false;
    }
}
