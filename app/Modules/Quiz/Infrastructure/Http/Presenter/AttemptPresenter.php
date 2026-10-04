<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Infrastructure\Http\Presenter;

use App\Modules\Quiz\Application\DTO\AttemptQuestionView;
use App\Modules\Quiz\Application\DTO\AttemptView;
use App\Modules\Quiz\Domain\ValueObject\AnswerResult;
use App\Modules\Quiz\Domain\ValueObject\Score;
use App\Modules\Quiz\Domain\ValueObject\SnapshotOption;

/** The one JSON shape of an attempt, shared by the student's quiz routes and the staff review route. */
final class AttemptPresenter
{
    /** @return array<string, mixed> */
    public function attempt(AttemptView $view): array
    {
        return [
            'id' => $view->id,
            'topic_id' => $view->topicId,
            'started_at' => $view->startedAt,
            'completed_at' => $view->completedAt,
            'score' => $this->score($view->score),
            'questions' => array_map(fn (AttemptQuestionView $q): array => [
                'id' => $q->id,
                'position' => $q->position,
                'type' => $q->type,
                'statement' => $q->statement,
                'options' => array_map(static fn (SnapshotOption $o): array => ['id' => $o->id, 'text' => $o->text], $q->options),
                // Before answering: nothing that hints at the answer, not even the explanation.
                'result' => $q->result === null ? null : $this->result($q->result),
            ], $view->questions),
        ];
    }

    /** @return array{question_id: string, option_id: string, correct: bool, correct_option_id: string, explanation: string|null} */
    public function result(AnswerResult $result): array
    {
        return [
            'question_id' => $result->questionId,
            'option_id' => $result->optionId,
            'correct' => $result->correct,
            'correct_option_id' => $result->correctOptionId,
            'explanation' => $result->explanation,
        ];
    }

    /** @return array{total: int, answered: int, correct: int} */
    public function score(Score $score): array
    {
        return ['total' => $score->total, 'answered' => $score->answered, 'correct' => $score->correct];
    }
}
