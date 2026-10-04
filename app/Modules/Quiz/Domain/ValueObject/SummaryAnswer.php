<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Domain\ValueObject;

use DateTimeImmutable;

/** One graded answer of a class to a bank question, with the snapshot it was given on. */
final readonly class SummaryAnswer
{
    /** @param list<SnapshotOption> $options */
    public function __construct(
        public string $studentId,
        public string $questionId,
        public string $attemptQuestionId,
        public string $type,
        public string $statement,
        public array $options,
        public string $correctOptionId,
        public string $chosenOptionId,
        public bool $correct,
        public DateTimeImmutable $answeredAt,
        public DateTimeImmutable $attemptStartedAt,
    ) {}
}
