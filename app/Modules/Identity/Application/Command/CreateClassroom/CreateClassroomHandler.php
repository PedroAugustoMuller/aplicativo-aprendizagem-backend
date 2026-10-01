<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Command\CreateClassroom;

use App\Modules\Identity\Application\DTO\ClassroomView;
use App\Modules\Identity\Domain\Entity\Classroom;
use App\Modules\Identity\Domain\Exception\AccessDeniedException;
use App\Modules\Identity\Domain\Exception\ClassroomNameAlreadyTakenException;
use App\Modules\Identity\Domain\Exception\ClassroomSubjectInactiveException;
use App\Modules\Identity\Domain\Repository\ClassroomRepository;
use App\Modules\Identity\Domain\ValueObject\ClassroomId;
use App\Modules\Identity\Domain\ValueObject\ClassroomName;
use App\Shared\Domain\Contract\SubjectCatalog;
use App\Shared\Domain\Exception\IdempotencyConflictException;

final readonly class CreateClassroomHandler
{
    public function __construct(
        private ClassroomRepository $classrooms,
        private SubjectCatalog $subjects,
    ) {}

    public function handle(CreateClassroomCommand $command): ClassroomView
    {
        if (! $command->actor->isAdmin()) {
            throw new AccessDeniedException;
        }

        $id = new ClassroomId($command->id);
        $name = new ClassroomName($command->name);
        $existing = $this->classrooms->findById($id);

        if ($existing !== null) {
            if ($existing->name()->value() !== $name->value() || $existing->subjectId() !== $command->subjectId) {
                throw new IdempotencyConflictException;
            }

            return ClassroomView::of($existing);
        }

        if (! $this->subjects->isActiveSubject($command->subjectId)) {
            throw new ClassroomSubjectInactiveException;
        }

        if ($this->classrooms->nameTakenByAnother($name, $id)) {
            throw new ClassroomNameAlreadyTakenException($name->value());
        }

        $classroom = Classroom::create($id, $name, $command->subjectId);
        $this->classrooms->save($classroom);

        return ClassroomView::of($classroom);
    }
}
