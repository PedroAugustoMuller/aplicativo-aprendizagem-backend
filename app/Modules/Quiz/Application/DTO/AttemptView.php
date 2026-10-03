<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Application\DTO;

use App\Modules\Quiz\Domain\Entity\Attempt;
use App\Modules\Quiz\Domain\ValueObject\Score;
use DateTimeInterface;

final readonly class AttemptView
{
    /** @param list<AttemptQuestionView> $questions */
    public function __construct(
        public string $id,
        public string $topicId,
        public string $startedAt,
        public ?string $completedAt,
        public Score $score,
        public array $questions,
    ) {}

    public static function of(Attempt $attempt): self
    {
        return new self(
            $attempt->id()->value(),
            $attempt->topicId(),
            $attempt->startedAt()->format(DateTimeInterface::ATOM),
            $attempt->completedAt()?->format(DateTimeInterface::ATOM),
            $attempt->score(),
            array_map(AttemptQuestionView::of(...), $attempt->questions()),
        );
    }
}
