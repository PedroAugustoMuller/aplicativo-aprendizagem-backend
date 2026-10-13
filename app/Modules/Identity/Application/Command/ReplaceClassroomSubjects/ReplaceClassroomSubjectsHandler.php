<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Command\ReplaceClassroomSubjects;

use App\Modules\Identity\Application\DTO\ClassroomView;
use App\Modules\Identity\Domain\Exception\AccessDeniedException;
use App\Modules\Identity\Domain\Exception\ClassroomNotFoundException;
use App\Modules\Identity\Domain\Exception\ClassroomSubjectInactiveException;
use App\Modules\Identity\Domain\Repository\ClassroomRepository;
use App\Modules\Identity\Domain\ValueObject\ClassroomId;
use App\Shared\Domain\Contract\SubjectCatalog;

/** Replaces a classroom's subjects; assignments to a removed subject go with it. */
final readonly class ReplaceClassroomSubjectsHandler
{
    public function __construct(
        private ClassroomRepository $classrooms,
        private SubjectCatalog $subjects,
    ) {}

    public function handle(ReplaceClassroomSubjectsCommand $command): ClassroomView
    {
        if (! $command->actor->isAdmin()) {
            throw new AccessDeniedException;
        }

        $classroom = $this->classrooms->findById(new ClassroomId($command->classroomId))
            ?? throw new ClassroomNotFoundException;
        $subjectIds = array_values(array_unique($command->subjectIds));

        // A subject already in the classroom stays even if Content deactivated it since.
        foreach ($subjectIds as $subjectId) {
            if (! $classroom->hasSubject($subjectId) && ! $this->subjects->isActiveSubject($subjectId)) {
                throw new ClassroomSubjectInactiveException;
            }
        }

        $classroom->replaceSubjects($subjectIds);
        $this->classrooms->save($classroom);

        return ClassroomView::of($classroom);
    }
}
