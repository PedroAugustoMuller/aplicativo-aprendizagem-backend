<?php

declare(strict_types=1);

namespace App\Shared\Domain\Contract;

/** A classroom as Quiz sees it: its subjects, who teaches what and who is enrolled. */
final readonly class RosterClassroom
{
    /**
     * @param  list<string>  $subjectIds
     * @param  list<RosterAssignment>  $assignments
     * @param  list<RosterStudent>  $students  enrolled, sorted by name
     */
    public function __construct(
        public string $id,
        public array $subjectIds,
        public bool $active,
        public array $assignments,
        public array $students,
    ) {}

    public function hasSubject(string $subjectId): bool
    {
        return in_array($subjectId, $this->subjectIds, true);
    }

    /** Any assignment. */
    public function isTaughtBy(string $userId): bool
    {
        foreach ($this->assignments as $assignment) {
            if ($assignment->teacherId === $userId) {
                return true;
            }
        }

        return false;
    }

    public function teaches(string $userId, string $subjectId): bool
    {
        return in_array($subjectId, $this->subjectsTaughtBy($userId), true);
    }

    /** @return list<string> the classroom's subjects this user teaches */
    public function subjectsTaughtBy(string $userId): array
    {
        $subjects = [];

        foreach ($this->assignments as $assignment) {
            if ($assignment->teacherId !== $userId) {
                continue;
            }

            if ($assignment->subjectId === null) {
                return $this->subjectIds;
            }

            if ($this->hasSubject($assignment->subjectId)) {
                $subjects[] = $assignment->subjectId;
            }
        }

        return $subjects;
    }

    public function student(string $studentId): ?RosterStudent
    {
        foreach ($this->students as $student) {
            if ($student->id === $studentId) {
                return $student;
            }
        }

        return null;
    }
}
