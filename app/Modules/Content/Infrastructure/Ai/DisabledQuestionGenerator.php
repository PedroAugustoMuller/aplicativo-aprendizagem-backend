<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Ai;

use App\Modules\Content\Application\Command\SuggestQuestions\QuestionGenerator;
use App\Modules\Content\Application\Command\SuggestQuestions\SuggestionRequest;
use App\Modules\Content\Domain\Exception\SuggestionsDisabledException;

final class DisabledQuestionGenerator implements QuestionGenerator
{
    public function generate(SuggestionRequest $request): array
    {
        throw new SuggestionsDisabledException;
    }
}
