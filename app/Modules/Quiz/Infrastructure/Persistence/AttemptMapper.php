<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Infrastructure\Persistence;

use App\Modules\Quiz\Domain\Entity\Attempt;
use App\Modules\Quiz\Domain\Entity\AttemptQuestion;
use App\Modules\Quiz\Domain\ValueObject\AnswerId;
use App\Modules\Quiz\Domain\ValueObject\AttemptId;
use App\Modules\Quiz\Domain\ValueObject\AttemptQuestionId;
use App\Modules\Quiz\Domain\ValueObject\SnapshotOption;
use App\Shared\Infrastructure\Persistence\EloquentAttribute;
use UnexpectedValueException;

final class AttemptMapper
{
    /** @param list<QuizAttemptQuestionModel> $rows in position order */
    public function toDomain(QuizAttemptModel $model, array $rows): Attempt
    {
        $questions = array_map(fn (QuizAttemptQuestionModel $row): AttemptQuestion => new AttemptQuestion(
            new AttemptQuestionId($row->id),
            $row->position,
            $row->question_id,
            $row->type,
            $row->statement,
            $row->explanation,
            $this->options($row->options),
            $row->correct_option_id,
            $row->answer_id === null ? null : new AnswerId($row->answer_id),
            $row->chosen_option_id,
            $row->answered_at,
        ), $rows);

        return Attempt::restore(
            new AttemptId($model->id),
            $model->student_id,
            $model->topic_id,
            $model->subject_id,
            $model->started_at,
            $model->completed_at,
            $questions,
        );
    }

    /** @return list<array{id: string, text: string}> */
    public function optionsToJson(AttemptQuestion $question): array
    {
        return array_map(static fn (SnapshotOption $o): array => ['id' => $o->id, 'text' => $o->text], $question->options());
    }

    /**
     * The snapshot options as decoded from their JSON column; also used by the progress reader.
     *
     * @return list<SnapshotOption>
     */
    public function options(mixed $raw): array
    {
        if (! is_array($raw)) {
            throw new UnexpectedValueException('Expected a list for quiz_attempt_questions.options.');
        }

        $options = [];

        foreach ($raw as $option) {
            if (! is_array($option)) {
                throw new UnexpectedValueException('Expected an object for quiz_attempt_questions.options[].');
            }

            $options[] = new SnapshotOption(
                EloquentAttribute::string($option['id'] ?? null, 'quiz_attempt_questions.options[].id'),
                EloquentAttribute::string($option['text'] ?? null, 'quiz_attempt_questions.options[].text'),
            );
        }

        return $options;
    }
}
