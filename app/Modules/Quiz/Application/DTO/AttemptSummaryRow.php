<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Application\DTO;

/** One attempt as a history list needs it; times are ATOM strings. */
final readonly class AttemptSummaryRow
{
    public function __construct(
        public string $id,
        public string $startedAt,
        public ?string $completedAt,
        public int $total,
        public int $answered,
        public int $correct,
    ) {}
}
