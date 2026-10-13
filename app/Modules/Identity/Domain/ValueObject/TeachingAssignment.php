<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\ValueObject;

/** A teacher in a classroom: every subject of it (null) or one subject of it. */
final readonly class TeachingAssignment
{
    public function __construct(
        public UserId $teacherId,
        public ?string $subjectId,
    ) {}

    public function isWholeClassroom(): bool
    {
        return $this->subjectId === null;
    }

    public function key(): string
    {
        return $this->teacherId->value().'|'.($this->subjectId ?? '*');
    }
}
