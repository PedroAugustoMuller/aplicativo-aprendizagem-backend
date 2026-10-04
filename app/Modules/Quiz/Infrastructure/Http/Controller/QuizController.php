<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Infrastructure\Http\Controller;

use App\Modules\Quiz\Application\Command\AnswerQuestion\AnswerQuestionCommand;
use App\Modules\Quiz\Application\Command\AnswerQuestion\AnswerQuestionHandler;
use App\Modules\Quiz\Application\Command\StartAttempt\StartAttemptCommand;
use App\Modules\Quiz\Application\Command\StartAttempt\StartAttemptHandler;
use App\Modules\Quiz\Application\Query\GetAttempt\GetAttemptHandler;
use App\Modules\Quiz\Application\Query\GetAttempt\GetAttemptQuery;
use App\Modules\Quiz\Infrastructure\Http\Presenter\AttemptPresenter;
use App\Modules\Quiz\Infrastructure\Http\Request\AnswerQuestionRequest;
use App\Modules\Quiz\Infrastructure\Http\Request\StartAttemptRequest;
use App\Shared\Infrastructure\Http\ActorFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class QuizController
{
    public function __construct(private readonly ActorFactory $actors, private readonly AttemptPresenter $presenter) {}

    public function start(StartAttemptRequest $request, string $id, StartAttemptHandler $handler): JsonResponse
    {
        $started = $handler->handle(new StartAttemptCommand($this->actors->fromRequest($request), $id, (string) $request->string('id')));

        return new JsonResponse(['data' => $this->presenter->attempt($started->view)], $started->created ? 201 : 200);
    }

    public function show(Request $request, string $id, GetAttemptHandler $handler): JsonResponse
    {
        return new JsonResponse(['data' => $this->presenter->attempt($handler->handle(new GetAttemptQuery($this->actors->fromRequest($request), $id)))]);
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

        return new JsonResponse(['data' => $this->presenter->result($outcome->result) + [
            'score' => $this->presenter->score($outcome->score),
            'completed' => $outcome->completed,
        ]]);
    }
}
