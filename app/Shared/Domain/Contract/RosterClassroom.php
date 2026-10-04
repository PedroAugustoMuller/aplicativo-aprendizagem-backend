<?php

declare(strict_types=1);

namespace App\Shared\Domain\Contract;

/** A classroom as Quiz sees it: its subject, who teaches it and who is enrolled. */
final readonly class RosterClassroom
{
    /**
     * @param  list<string>  $teacherIds
     * @param  list<RosterStudent>  $students  enrolled, sorted by name
     */
    public function __construct(
        public string $id,
        public string $subjectId,
        public bool $active,
        public array $teacherIds,
        public array $students,
    ) {}

    public function isTaughtBy(string $userId): bool
    {
        return in_array($userId, $this->teacherIds, true);
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
