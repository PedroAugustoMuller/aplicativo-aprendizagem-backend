<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Command\SuggestQuestions;

final readonly class SuggestionResult
{
    /** @param list<QuestionSuggestion> $kept */
    public function __construct(public array $kept, public int $received) {}
}
