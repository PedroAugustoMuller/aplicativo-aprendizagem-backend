<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Application\Service;

/** Whose answers a progress read sees, and in which subject (null: every subject, the student's own view). */
final readonly class ProgressScope
{
    public function __construct(
        public string $studentId,
        public ?string $subjectId,
    ) {}
}
