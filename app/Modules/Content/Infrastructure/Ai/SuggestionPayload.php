<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Ai;

use App\Modules\Content\Application\Command\SuggestQuestions\QuestionSuggestion;
use App\Modules\Content\Domain\ValueObject\OptionDraft;
use App\Modules\Content\Domain\ValueObject\QuestionType;

/**
 * The "questions" list every provider is asked to return, read defensively:
 * an item of the wrong shape is skipped, never trusted. Whether a question
 * follows the bank's rules is SuggestionFilter's job, not this one's.
 */
final class SuggestionPayload
{
    /**
     * @param  array<array-key, mixed>  $items
     * @return list<QuestionSuggestion>
     */
    public static function questions(array $items): array
    {
        $suggestions = [];

        foreach ($items as $item) {
            $suggestion = self::suggestion($item);

            if ($suggestion !== null) {
                $suggestions[] = $suggestion;
            }
        }

        return $suggestions;
    }

    private static function suggestion(mixed $item): ?QuestionSuggestion
    {
        if (! is_array($item) || ! is_string($item['type'] ?? null) || ! is_string($item['statement'] ?? null)) {
            return null;
        }

        $type = QuestionType::tryFrom($item['type']);
        $explanation = is_string($item['explanation'] ?? null) ? $item['explanation'] : null;

        if ($type === QuestionType::TrueFalse) {
            $answer = $item['answer'] ?? null;

            return new QuestionSuggestion($type, $item['statement'], $explanation, [], is_bool($answer) ? $answer : null);
        }

        if ($type === QuestionType::MultipleChoice && is_array($item['options'] ?? null)) {
            $options = [];

            foreach ($item['options'] as $option) {
                if (is_array($option) && is_string($option['text'] ?? null) && is_bool($option['correct'] ?? null)) {
                    $options[] = new OptionDraft(null, $option['text'], $option['correct']);
                }
            }

            return new QuestionSuggestion($type, $item['statement'], $explanation, $options, null);
        }

        return null;
    }
}
