<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Application\DTO;

use App\Modules\Quiz\Domain\ValueObject\SnapshotOption;
use DateTimeImmutable;

/** One answered snapshot row that still links to its bank question. */
final readonly class AnsweredQuestionRow
{
    /** @param list<SnapshotOption> $options */
    public function __construct(
        public string $questionId,
        public string $type,
        public string $statement,
        public array $options,
        public string $chosenOptionId,
        public string $correctOptionId,
        public ?string $explanation,
        public bool $correct,
        public DateTimeImmutable $answeredAt,
        public DateTimeImmutable $attemptStartedAt,
    ) {}
}
