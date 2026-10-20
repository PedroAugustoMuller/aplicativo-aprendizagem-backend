<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Command\SuggestQuestions;

use App\Modules\Content\Domain\ValueObject\OptionDraft;
use App\Modules\Content\Domain\ValueObject\QuestionType;

/**
 * A question as a generator proposed it. Unchecked until SuggestionFilter keeps
 * it; never stored. Options: multiple choice only. Answer: true/false only.
 */
final readonly class QuestionSuggestion
{
    /** @param list<OptionDraft> $options */
    public function __construct(
        public QuestionType $type,
        public string $statement,
        public ?string $explanation,
        public array $options,
        public ?bool $answer,
    ) {}
}
