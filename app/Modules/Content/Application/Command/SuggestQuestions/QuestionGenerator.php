<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Command\SuggestQuestions;

use App\Modules\Content\Domain\Exception\SuggestionsDisabledException;
use App\Modules\Content\Domain\Exception\SuggestionsUnavailableException;

/** The AI behind "Gerar com IA". Which one is AI_PROVIDER's choice (ContentServiceProvider). */
interface QuestionGenerator
{
    /**
     * @return list<QuestionSuggestion> as proposed, unchecked
     *
     * @throws SuggestionsUnavailableException
     * @throws SuggestionsDisabledException
     */
    public function generate(SuggestionRequest $request): array;
}
