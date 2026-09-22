<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Command\UpdateClassroom;

use App\Modules\Identity\Application\DTO\ClassroomView;
use App\Modules\Identity\Domain\Exception\AccessDeniedException;
use App\Modules\Identity\Domain\Exception\ClassroomNameAlreadyTakenException;
use App\Modules\Identity\Domain\Exception\ClassroomNotFoundException;
use App\Modules\Identity\Domain\Exception\ClassroomSubjectInactiveException;
use App\Modules\Identity\Domain\Repository\ClassroomRepository;
use App\Modules\Identity\Domain\ValueObject\ClassroomId;
use App\Modules\Identity\Domain\ValueObject\ClassroomName;
use App\Shared\Domain\Contract\SubjectCatalog;

final readonly class UpdateClassroomHandler
{
    public function __construct(
        private ClassroomRepository $classrooms,
        private SubjectCatalog $subjects,
    ) {}

    public function handle(UpdateClassroomCommand $command): ClassroomView
    {
        if (! $command->actor->isAdmin()) {
            throw new AccessDeniedException;
        }

        $id = new ClassroomId($command->id);
        $classroom = $this->classrooms->findById($id) ?? throw new ClassroomNotFoundException;
        $name = new ClassroomName($command->name);

        if ($command->subjectId !== $classroom->subjectId() && ! $this->subjects->isActiveSubject($command->subjectId)) {
            throw new ClassroomSubjectInactiveException;
        }

        if ($this->classrooms->nameTakenByAnother($name, $id)) {
            throw new ClassroomNameAlreadyTakenException($name->value());
        }

        $classroom->rename($name);
        $classroom->changeSubject($command->subjectId);
        $this->classrooms->save($classroom);

        return ClassroomView::of($classroom);
    }
}
