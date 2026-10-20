<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Modules\Content\Application\Command\SuggestQuestions\QuestionGenerator;
use App\Modules\Content\Application\Command\SuggestQuestions\QuestionSuggestion;
use App\Modules\Content\Application\Command\SuggestQuestions\SuggestionRequest;

/** Answers fixed suggestions and keeps the last request it was given. */
final class RecordingQuestionGenerator implements QuestionGenerator
{
    public ?SuggestionRequest $last = null;

    /** @param list<QuestionSuggestion> $suggestions */
    public function __construct(private readonly array $suggestions) {}

    public function generate(SuggestionRequest $request): array
    {
        $this->last = $request;

        return $this->suggestions;
    }
}
