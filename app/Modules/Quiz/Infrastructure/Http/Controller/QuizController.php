<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Infrastructure\Http\Controller;

use App\Modules\Quiz\Application\Command\AnswerQuestion\AnswerQuestionCommand;
use App\Modules\Quiz\Application\Command\AnswerQuestion\AnswerQuestionHandler;
use App\Modules\Quiz\Application\Command\StartAttempt\StartAttemptCommand;
use App\Modules\Quiz\Application\Command\StartAttempt\StartAttemptHandler;
use App\Modules\Quiz\Application\DTO\AttemptQuestionView;
use App\Modules\Quiz\Application\DTO\AttemptView;
use App\Modules\Quiz\Application\Query\GetAttempt\GetAttemptHandler;
use App\Modules\Quiz\Application\Query\GetAttempt\GetAttemptQuery;
use App\Modules\Quiz\Domain\ValueObject\AnswerResult;
use App\Modules\Quiz\Domain\ValueObject\Score;
use App\Modules\Quiz\Domain\ValueObject\SnapshotOption;
use App\Modules\Quiz\Infrastructure\Http\Request\AnswerQuestionRequest;
use App\Modules\Quiz\Infrastructure\Http\Request\StartAttemptRequest;
use App\Shared\Infrastructure\Http\ActorFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class QuizController
{
    public function __construct(private readonly ActorFactory $actors) {}

    public function start(StartAttemptRequest $request, string $id, StartAttemptHandler $handler): JsonResponse
    {
        $started = $handler->handle(new StartAttemptCommand($this->actors->fromRequest($request), $id, (string) $request->string('id')));

        return new JsonResponse(['data' => $this->attempt($started->view)], $started->created ? 201 : 200);
    }

    public function show(Request $request, string $id, GetAttemptHandler $handler): JsonResponse
    {
        return new JsonResponse(['data' => $this->attempt($handler->handle(new GetAttemptQuery($this->actors->fromRequest($request), $id)))]);
    }

    public function answer(AnswerQuestionRequest $request, string $id, AnswerQuestionHandler $handler): JsonResponse
    {
        $outcome = $handler->handle(new AnswerQuestionCommand(
            $this->actors->fromRequest($request),
            $id,
            (string) $request->string('answer_id'),
            (string) $request->string('question_id'),
            (string) $request->string('option_id'),
            $request->answeredAt(),
        ));

        return new JsonResponse(['data' => $this->result($outcome->result) + [
            'score' => $this->score($outcome->score),
            'completed' => $outcome->completed,
        ]]);
    }

    /** @return array<string, mixed> */
    private function attempt(AttemptView $view): array
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
    private function result(AnswerResult $result): array
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
    private function score(Score $score): array
    {
        return ['total' => $score->total, 'answered' => $score->answered, 'correct' => $score->correct];
    }
}
