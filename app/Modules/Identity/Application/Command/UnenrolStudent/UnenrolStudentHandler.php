<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Command\UnenrolStudent;

use App\Modules\Identity\Application\DTO\ClassroomView;
use App\Modules\Identity\Domain\Exception\AccessDeniedException;
use App\Modules\Identity\Domain\Exception\ClassroomNotFoundException;
use App\Modules\Identity\Domain\Policy\RosterPolicy;
use App\Modules\Identity\Domain\Repository\ClassroomRepository;
use App\Modules\Identity\Domain\ValueObject\ClassroomId;
use App\Modules\Identity\Domain\ValueObject\UserId;

final readonly class UnenrolStudentHandler
{
    public function __construct(
        private ClassroomRepository $classrooms,
        private RosterPolicy $policy,
    ) {}

    public function handle(UnenrolStudentCommand $command): ClassroomView
    {
        $classroom = $this->classrooms->findById(new ClassroomId($command->classroomId));
        if ($classroom === null || ! $classroom->isActive()) {
            throw new ClassroomNotFoundException;
        }

        if (! $this->policy->canManageClassroom($command->actor, $classroom)) {
            throw new AccessDeniedException;
        }

        // Unenrolling a student not in the classroom is a no-op: unset() on a
        // missing key does nothing, so this never fails as "not found".
        $classroom->unenrol(new UserId($command->studentId));
        $this->classrooms->save($classroom);

        return ClassroomView::of($classroom);
    }
}
