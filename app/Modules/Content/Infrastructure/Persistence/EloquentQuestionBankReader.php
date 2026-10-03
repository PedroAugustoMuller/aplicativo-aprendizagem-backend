<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Persistence;

use App\Modules\Content\Application\DTO\QuestionOptionView;
use App\Modules\Content\Application\DTO\QuestionView;
use App\Modules\Content\Application\Query\ListQuestions\QuestionBankReader;
use App\Shared\Infrastructure\Persistence\EloquentAttribute;

/** Two queries for the whole bank — questions, then all their options — never one per question. */
final class EloquentQuestionBankReader implements QuestionBankReader
{
    public function forTopic(string $topicId): array
    {
        $questions = QuestionModel::query()
            ->select(['id', 'topic_id', 'type', 'statement', 'explanation', 'version', 'deactivated_at', 'created_at'])
            ->where('topic_id', $topicId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $ids = [];

        foreach ($questions as $question) {
            $ids[] = EloquentAttribute::string($question->getKey(), 'questions.id');
        }

        $optionsByQuestion = [];

        if ($ids !== []) {
            foreach (QuestionOptionModel::query()->whereIn('question_id', $ids)->orderBy('position')->get() as $option) {
                $questionId = EloquentAttribute::string($option->getAttribute('question_id'), 'question_options.question_id');
                $optionsByQuestion[$questionId][] = new QuestionOptionView(
                    EloquentAttribute::string($option->getKey(), 'question_options.id'),
                    EloquentAttribute::string($option->getAttribute('text'), 'question_options.text'),
                    $option->is_correct,
                    $option->position,
                );
            }
        }

        $items = [];

        foreach ($questions as $question) {
            $id = EloquentAttribute::string($question->getKey(), 'questions.id');
            $explanation = $question->getAttribute('explanation');

            $items[] = new QuestionView(
                $id,
                EloquentAttribute::string($question->getAttribute('topic_id'), 'questions.topic_id'),
                EloquentAttribute::string($question->getAttribute('type'), 'questions.type'),
                EloquentAttribute::string($question->getAttribute('statement'), 'questions.statement'),
                is_string($explanation) ? $explanation : null,
                $question->getAttribute('deactivated_at') === null,
                $question->version,
                $optionsByQuestion[$id] ?? [],
            );
        }

        return $items;
    }
}
