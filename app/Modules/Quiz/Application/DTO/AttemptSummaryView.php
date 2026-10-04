<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Application\DTO;

use App\Modules\Quiz\Domain\ValueObject\AttemptPoints;
use App\Modules\Quiz\Domain\ValueObject\Tier;

final readonly class AttemptSummaryView
{
    public function __construct(
        public string $id,
        public string $startedAt,
        public ?string $completedAt,
        public int $total,
        public int $answered,
        public int $correct,
        public int $pointsBefore,
        public int $pointsAfter,
        public Tier $tierBefore,
        public Tier $tierAfter,
    ) {}

    public static function of(AttemptSummaryRow $row, AttemptPoints $points): self
    {
        return new self(
            $row->id, $row->startedAt, $row->completedAt, $row->total, $row->answered, $row->correct,
            $points->before, $points->after, Tier::forPoints($points->before), Tier::forPoints($points->after),
        );
    }

    public function pointsChange(): int
    {
        return $this->pointsAfter - $this->pointsBefore;
    }
}
