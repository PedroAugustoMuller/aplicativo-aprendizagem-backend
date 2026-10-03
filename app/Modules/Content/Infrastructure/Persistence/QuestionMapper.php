<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Persistence;

use App\Modules\Content\Domain\Entity\Question;
use App\Modules\Content\Domain\Entity\QuestionOption;
use App\Modules\Content\Domain\ValueObject\OptionText;
use App\Modules\Content\Domain\ValueObject\QuestionId;
use App\Modules\Content\Domain\ValueObject\QuestionOptionId;
use App\Modules\Content\Domain\ValueObject\QuestionStatement;
use App\Modules\Content\Domain\ValueObject\QuestionType;
use App\Modules\Content\Domain\ValueObject\TopicId;
use App\Shared\Infrastructure\Persistence\EloquentAttribute;
use DateTimeImmutable;

final class QuestionMapper
{
    /** @param list<QuestionOptionModel> $options ordered by position */
    public function toDomain(QuestionModel $model, array $options): Question
    {
        $explanation = $model->getAttribute('explanation');
        $deactivatedAt = $model->getAttribute('deactivated_at');

        return Question::restore(
            new QuestionId(EloquentAttribute::string($model->getKey(), 'questions.id')),
            new TopicId(EloquentAttribute::string($model->getAttribute('topic_id'), 'questions.topic_id')),
            QuestionType::from(EloquentAttribute::string($model->getAttribute('type'), 'questions.type')),
            new QuestionStatement(EloquentAttribute::string($model->getAttribute('statement'), 'questions.statement')),
            is_string($explanation) ? $explanation : null,
            array_map(fn (QuestionOptionModel $option): QuestionOption => new QuestionOption(
                new QuestionOptionId(EloquentAttribute::string($option->getKey(), 'question_options.id')),
                new OptionText(EloquentAttribute::string($option->getAttribute('text'), 'question_options.text')),
                $option->is_correct,
                $option->position,
            ), $options),
            $model->version,
            $deactivatedAt instanceof DateTimeImmutable ? $deactivatedAt : null,
        );
    }

    /** @return array<string, int|string|DateTimeImmutable|null> */
    public function toAttributes(Question $question): array
    {
        return [
            'topic_id' => $question->topicId()->value(),
            'type' => $question->type()->value,
            'statement' => $question->statement()->value(),
            'explanation' => $question->explanation(),
            'version' => $question->version(),
            'deactivated_at' => $question->deactivatedAt(),
        ];
    }
}
