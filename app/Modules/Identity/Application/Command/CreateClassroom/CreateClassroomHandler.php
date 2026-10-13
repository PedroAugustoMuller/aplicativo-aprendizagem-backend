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
        $subjectIds = array_values(array_unique($command->subjectIds));
        $existing = $this->classrooms->findById($id);

        if ($existing !== null) {
            if ($existing->name()->value() !== $name->value() || ! self::sameSet($existing->subjectIds(), $subjectIds)) {
                throw new IdempotencyConflictException;
            }

            return ClassroomView::of($existing);
        }

        foreach ($subjectIds as $subjectId) {
            if (! $this->subjects->isActiveSubject($subjectId)) {
                throw new ClassroomSubjectInactiveException;
            }
        }

        if ($this->classrooms->nameTakenByAnother($name, $id)) {
            throw new ClassroomNameAlreadyTakenException($name->value());
        }

        $classroom = Classroom::create($id, $name, $subjectIds);
        $this->classrooms->save($classroom);

        return ClassroomView::of($classroom);
    }

    /**
     * @param  list<string>  $a
     * @param  list<string>  $b
     */
    private static function sameSet(array $a, array $b): bool
    {
        sort($a);
        sort($b);

        return $a === $b;
    }
}
