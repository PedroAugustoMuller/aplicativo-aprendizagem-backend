<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Domain\ValueObject;

use DateTimeImmutable;

/** One graded answer, as much of it as scoring needs. `id` is the attempt question id. */
final readonly class ScoredAnswer
{
    public function __construct(
        public string $attemptId,
        public string $id,
        public int $position,
        public bool $correct,
        public DateTimeImmutable $answeredAt,
    ) {}
}
