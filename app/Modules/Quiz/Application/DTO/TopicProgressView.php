<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Application\DTO;

use App\Modules\Quiz\Domain\Service\Progress;
use App\Modules\Quiz\Domain\ValueObject\Tier;

final readonly class TopicProgressView
{
    public function __construct(
        public string $topicId,
        public int $points,
        public Tier $tier,
        public ?Tier $nextTier,
    ) {}

    public static function of(string $topicId, Progress $progress): self
    {
        return new self($topicId, $progress->points, $progress->tier(), $progress->nextTier());
    }
}
