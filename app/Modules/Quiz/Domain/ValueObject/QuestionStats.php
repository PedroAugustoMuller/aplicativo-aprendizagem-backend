<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Domain\ValueObject;

/** How a group of students did on one bank question, counting each student's latest answer. */
final readonly class QuestionStats
{
    /** @param list<OptionCount> $options of the most recent snapshot */
    public function __construct(
        public string $questionId,
        public string $type,
        public string $statement,
        public string $correctOptionId,
        public int $answered,
        public int $wrong,
        public array $options,
        public int $otherChosen,
    ) {}

    /** Whole percent, half up: 1 of 8 is 13. */
    public function wrongPercent(): int
    {
        return $this->answered === 0 ? 0 : intdiv(200 * $this->wrong + $this->answered, 2 * $this->answered);
    }
}
