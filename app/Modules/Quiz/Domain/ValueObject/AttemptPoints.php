<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Domain\ValueObject;

/** The topic balance just before an attempt's first answer and just after its last. */
final readonly class AttemptPoints
{
    public function __construct(
        public int $before,
        public int $after,
    ) {}

    /** What the attempt really moved, floor included. */
    public function change(): int
    {
        return $this->after - $this->before;
    }
}
