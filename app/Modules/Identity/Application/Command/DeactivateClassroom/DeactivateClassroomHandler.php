<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Command\DeactivateClassroom;

use App\Modules\Identity\Application\DTO\ClassroomView;
use App\Modules\Identity\Domain\Exception\AccessDeniedException;
use App\Modules\Identity\Domain\Exception\ClassroomNotFoundException;
use App\Modules\Identity\Domain\Repository\ClassroomRepository;
use App\Modules\Identity\Domain\ValueObject\ClassroomId;
use DateTimeImmutable;

final readonly class DeactivateClassroomHandler
{
    public function __construct(private ClassroomRepository $classrooms) {}

    public function handle(DeactivateClassroomCommand $command): ClassroomView
    {
        if (! $command->actor->isAdmin()) {
            throw new AccessDeniedException;
        }

        $classroom = $this->classrooms->findById(new ClassroomId($command->id)) ?? throw new ClassroomNotFoundException;

        $classroom->deactivate(new DateTimeImmutable);
        $this->classrooms->save($classroom);

        return ClassroomView::of($classroom);
    }
}
