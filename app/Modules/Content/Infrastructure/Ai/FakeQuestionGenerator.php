<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Ai;

use App\Modules\Content\Application\Command\SuggestQuestions\QuestionGenerator;
use App\Modules\Content\Application\Command\SuggestQuestions\QuestionSuggestion;
use App\Modules\Content\Application\Command\SuggestQuestions\SuggestionRequest;
use App\Modules\Content\Domain\ValueObject\OptionDraft;
use App\Modules\Content\Domain\ValueObject\QuestionType;

/**
 * AI_PROVIDER=fake: fixed, valid questions with no network — development without
 * a key and the frontend e2e suite. Alternates the requested types.
 */
final class FakeQuestionGenerator implements QuestionGenerator
{
    public function generate(SuggestionRequest $request): array
    {
        $suggestions = [];

        for ($n = 1; $n <= $request->count; $n++) {
            $type = $request->types[($n - 1) % count($request->types)];
            $statement = sprintf('Questão de exemplo %d sobre %s', $n, $request->topicName);

            $suggestions[] = $type === QuestionType::TrueFalse
                ? new QuestionSuggestion($type, $statement.' (verdadeiro ou falso).', 'Explicação de exemplo.', [], $n % 2 === 0)
                : new QuestionSuggestion($type, $statement.'?', 'Explicação de exemplo.', [
                    new OptionDraft(null, 'Alternativa certa', true),
                    new OptionDraft(null, 'Alternativa errada A', false),
                    new OptionDraft(null, 'Alternativa errada B', false),
                    new OptionDraft(null, 'Alternativa errada C', false),
                ], null);
        }

        return $suggestions;
    }
}
