<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Domain\ValueObject;

/** What the student learns after answering. Never available before. */
final readonly class AnswerResult
{
    public function __construct(
        public string $questionId,
        public string $optionId,
        public bool $correct,
        public string $correctOptionId,
        public ?string $explanation,
    ) {}
}
