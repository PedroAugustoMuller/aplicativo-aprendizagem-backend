<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Command\EnrolStudent;

use App\Modules\Identity\Application\DTO\ClassroomView;
use App\Modules\Identity\Domain\Exception\AccessDeniedException;
use App\Modules\Identity\Domain\Exception\ClassroomNotFoundException;
use App\Modules\Identity\Domain\Exception\StudentNotFoundException;
use App\Modules\Identity\Domain\Policy\RosterPolicy;
use App\Modules\Identity\Domain\Repository\ClassroomRepository;
use App\Modules\Identity\Domain\Repository\UserRepository;
use App\Modules\Identity\Domain\ValueObject\ClassroomId;
use App\Modules\Identity\Domain\ValueObject\UserId;
use App\Shared\Domain\Auth\Role;

/**
 * Enrolling only needs rights over the TARGET classroom — a teacher may enrol a
 * student who is in none of their classrooms. That is how a student moves
 * between classrooms: unenrol from one, enrol into the other.
 */
final readonly class EnrolStudentHandler
{
    public function __construct(
        private ClassroomRepository $classrooms,
        private UserRepository $users,
        private RosterPolicy $policy,
    ) {}

    public function handle(EnrolStudentCommand $command): ClassroomView
    {
        $classroom = $this->classrooms->findById(new ClassroomId($command->classroomId));
        if ($classroom === null || ! $classroom->isActive()) {
            throw new ClassroomNotFoundException;
        }

        if (! $this->policy->canManageClassroom($command->actor, $classroom)) {
            throw new AccessDeniedException;
        }

        $student = $this->users->findById(new UserId($command->studentId));
        if ($student === null || $student->role() !== Role::Student) {
            throw new StudentNotFoundException;
        }

        $classroom->enrol($student);
        $this->classrooms->save($classroom);

        return ClassroomView::of($classroom);
    }
}
