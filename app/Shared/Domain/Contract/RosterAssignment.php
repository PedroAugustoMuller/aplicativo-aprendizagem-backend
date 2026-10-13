<?php

declare(strict_types=1);

namespace App\Shared\Domain\Contract;

/** A teacher of a classroom: every subject of it (null) or one subject of it. */
final readonly class RosterAssignment
{
    public function __construct(
        public string $teacherId,
        public ?string $subjectId,
    ) {}
}
