<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Application\Command\AnswerQuestion;

use App\Shared\Domain\Auth\Actor;
use DateTimeImmutable;

final readonly class AnswerQuestionCommand
{
    /** @param DateTimeImmutable $answeredAt as the device reported it; the domain bounds it */
    public function __construct(
        public Actor $actor,
        public string $attemptId,
        public string $answerId,
        public string $questionId,
        public string $optionId,
        public DateTimeImmutable $answeredAt,
    ) {}
}
