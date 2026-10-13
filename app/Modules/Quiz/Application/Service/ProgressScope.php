<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Application\Service;

/** Whose answers a progress read sees, and in which subjects (null: every subject, the student's own view). */
final readonly class ProgressScope
{
    /** @param  list<string>|null  $subjectIds */
    public function __construct(
        public string $studentId,
        public ?array $subjectIds,
    ) {}

    public function covers(string $subjectId): bool
    {
        return $this->subjectIds === null || in_array($subjectId, $this->subjectIds, true);
    }
}
