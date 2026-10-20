<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Command\SuggestQuestions;

use App\Modules\Content\Domain\ValueObject\QuestionType;

/** Everything a generator may use. Teaching material and names only — never people's data. */
final readonly class SuggestionRequest
{
    /** @param list<QuestionType> $types */
    public function __construct(
        public string $subjectName,
        public string $topicName,
        public string $topicDescription,
        public int $count,
        public array $types,
        public SuggestionDifficulty $difficulty,
        public ?string $notes,
        public ?string $sourceText,
    ) {}
}
