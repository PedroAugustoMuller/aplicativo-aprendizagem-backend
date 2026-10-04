<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Application\DTO;

use App\Modules\Quiz\Domain\ValueObject\Tier;

final readonly class TopicHistoryView
{
    /** @param list<AttemptSummaryView> $attempts newest first */
    public function __construct(
        public int $points,
        public Tier $tier,
        public ?Tier $nextTier,
        public array $attempts,
    ) {}
}
