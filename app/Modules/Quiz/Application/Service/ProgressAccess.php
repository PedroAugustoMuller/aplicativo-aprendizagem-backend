<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Application\Service;

use App\Modules\Quiz\Domain\Exception\ClassroomNotFoundException;
use App\Modules\Quiz\Domain\Exception\QuizAccessDeniedException;
use App\Modules\Quiz\Domain\Exception\StudentNotFoundException;
use App\Shared\Domain\Auth\Actor;
use App\Shared\Domain\Contract\ClassroomRoster;
use App\Shared\Domain\Contract\RosterClassroom;

/** Decides whose progress an actor may read. Behind role:* too: the middleware is the early reject, this is the rule. */
final readonly class ProgressAccess
{
    public function __construct(private ClassroomRoster $roster) {}

    /** A student reads their own answers, in any subject, enrolled or not any more. */
    public function ownScope(Actor $actor): ProgressScope
    {
        if (! $actor->isStudent()) {
            throw new QuizAccessDeniedException;
        }

        return new ProgressScope($actor->userId, null);
    }

    public function staff(Actor $actor): void
    {
        if (! $actor->isStaff()) {
            throw new QuizAccessDeniedException;
        }
    }

    /** Staff only: an admin reads any classroom, a teacher only the ones they teach. */
    public function classroom(Actor $actor, string $classroomId): RosterClassroom
    {
        $this->staff($actor);

        $classroom = $this->roster->find($classroomId) ?? throw new ClassroomNotFoundException;

        if (! $actor->isAdmin() && ! $classroom->isTaughtBy($actor->userId)) {
            throw new QuizAccessDeniedException;
        }

        return $classroom;
    }

    /**
     * The students of every active classroom of the subject the actor may read, each once.
     * A teacher who teaches none of them may not read the subject; an admin just sees nobody.
     *
     * @return list<string>
     */
    public function subjectStudents(Actor $actor, string $subjectId): array
    {
        $this->staff($actor);
        $readable = array_filter(
            $this->roster->forSubject($subjectId),
            static fn (RosterClassroom $c): bool => $c->active && ($actor->isAdmin() || $c->isTaughtBy($actor->userId)),
        );

        if ($readable === [] && ! $actor->isAdmin()) {
            throw new QuizAccessDeniedException;
        }

        $ids = [];

        foreach ($readable as $classroom) {
            foreach ($classroom->students as $student) {
                $ids[$student->id] = $student->id;
            }
        }

        return array_values($ids);
    }

    /**
     * Without a classroom: the actor's own answers. With one: a student enrolled in it,
     * seen only within the classroom's subject.
     */
    public function scopeFor(Actor $actor, ?string $classroomId, ?string $studentId): ProgressScope
    {
        if ($classroomId === null) {
            return $this->ownScope($actor);
        }

        $classroom = $this->classroom($actor, $classroomId);
        $student = $studentId === null ? null : $classroom->student($studentId);

        if ($student === null) {
            throw new StudentNotFoundException;
        }

        return new ProgressScope($student->id, $classroom->subjectId);
    }
}
