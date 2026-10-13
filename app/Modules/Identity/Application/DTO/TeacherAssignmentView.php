<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\DTO;

use App\Modules\Identity\Domain\ValueObject\TeachingAssignment;

/** A teacher of a classroom: every subject of it (null) or one subject of it. */
final readonly class TeacherAssignmentView
{
    public function __construct(
        public string $teacherId,
        public ?string $subjectId,
    ) {}

    public static function of(TeachingAssignment $assignment): self
    {
        return new self($assignment->teacherId->value(), $assignment->subjectId);
    }
}
