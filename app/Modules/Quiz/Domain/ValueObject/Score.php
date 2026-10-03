<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Domain\ValueObject;

final readonly class Score
{
    public function __construct(
        public int $total,
        public int $answered,
        public int $correct,
    ) {}
}
