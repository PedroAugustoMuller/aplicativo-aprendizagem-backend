<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Command\SuggestQuestions;

use App\Modules\Content\Domain\Entity\Question;
use App\Modules\Content\Domain\Entity\QuestionOption;
use App\Modules\Content\Domain\ValueObject\OptionDraft;
use App\Modules\Content\Domain\ValueObject\QuestionId;
use App\Modules\Content\Domain\ValueObject\QuestionStatement;
use App\Modules\Content\Domain\ValueObject\QuestionType;
use App\Modules\Content\Domain\ValueObject\TopicId;
use App\Shared\Domain\Exception\DomainException;
use InvalidArgumentException;

/**
 * Keeps what the question bank would accept, by building the real aggregate
 * (never saved) — so an AI suggestion obeys exactly the rules a typed question does.
 */
final readonly class SuggestionFilter
{
    /**
     * @param  list<QuestionSuggestion>  $raw
     * @param  list<QuestionType>  $types
     * @return list<QuestionSuggestion>
     */
    public function keep(array $raw, TopicId $topicId, array $types, int $count): array
    {
        $kept = [];

        foreach ($raw as $suggestion) {
            if (count($kept) >= $count) {
                break;
            }

            if (! in_array($suggestion->type, $types, true)) {
                continue;
            }

            $question = $this->build($suggestion, $topicId);

            if ($question !== null) {
                $kept[] = $this->normalized($question);
            }
        }

        return $kept;
    }

    private function build(QuestionSuggestion $suggestion, TopicId $topicId): ?Question
    {
        try {
            $statement = new QuestionStatement($suggestion->statement);

            return match ($suggestion->type) {
                QuestionType::MultipleChoice => Question::multipleChoice(
                    QuestionId::random(), $topicId, $statement, $suggestion->explanation, $suggestion->options,
                ),
                QuestionType::TrueFalse => $suggestion->answer === null
                    ? null
                    : Question::trueFalse(QuestionId::random(), $topicId, $statement, $suggestion->explanation, $suggestion->answer),
            };
        } catch (DomainException|InvalidArgumentException) {
            return null;
        }
    }

    private function normalized(Question $question): QuestionSuggestion
    {
        $options = $question->options();

        if ($question->type() === QuestionType::TrueFalse) {
            return new QuestionSuggestion(
                QuestionType::TrueFalse, $question->statement()->value(), $question->explanation(), [], $options[0]->correct,
            );
        }

        return new QuestionSuggestion(
            QuestionType::MultipleChoice,
            $question->statement()->value(),
            $question->explanation(),
            array_map(static fn (QuestionOption $o): OptionDraft => new OptionDraft(null, $o->text->value(), $o->correct), $options),
            null,
        );
    }
}
