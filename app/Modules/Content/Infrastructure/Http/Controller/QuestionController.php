<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Http\Controller;

use App\Modules\Content\Application\Command\CreateQuestion\CreateQuestionCommand;
use App\Modules\Content\Application\Command\CreateQuestion\CreateQuestionHandler;
use App\Modules\Content\Application\Command\SetQuestionActivation\SetQuestionActivationCommand;
use App\Modules\Content\Application\Command\SetQuestionActivation\SetQuestionActivationHandler;
use App\Modules\Content\Application\Command\UpdateQuestion\UpdateQuestionCommand;
use App\Modules\Content\Application\Command\UpdateQuestion\UpdateQuestionHandler;
use App\Modules\Content\Application\DTO\QuestionOptionView;
use App\Modules\Content\Application\DTO\QuestionView;
use App\Modules\Content\Application\Query\ListQuestions\ListQuestionsHandler;
use App\Modules\Content\Application\Query\ListQuestions\ListQuestionsQuery;
use App\Modules\Content\Domain\Repository\QuestionRepository;
use App\Modules\Content\Domain\ValueObject\QuestionId;
use App\Modules\Content\Infrastructure\Http\Request\CreateQuestionRequest;
use App\Modules\Content\Infrastructure\Http\Request\UpdateQuestionRequest;
use App\Shared\Infrastructure\Http\ActorFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class QuestionController
{
    public function __construct(private readonly ActorFactory $actors) {}

    public function index(Request $request, string $id, ListQuestionsHandler $handler): JsonResponse
    {
        $views = $handler->handle(new ListQuestionsQuery($this->actors->fromRequest($request), $id));

        return new JsonResponse(['data' => array_map(fn (QuestionView $v): array => $this->present($v), $views)]);
    }

    public function store(CreateQuestionRequest $request, string $id, CreateQuestionHandler $handler, QuestionRepository $questions): JsonResponse
    {
        $existed = $questions->findById(new QuestionId((string) $request->string('id'))) !== null;

        $view = $handler->handle(new CreateQuestionCommand(
            $this->actors->fromRequest($request),
            $id,
            (string) $request->string('id'),
            (string) $request->string('type'),
            (string) $request->string('statement'),
            $request->explanationText(),
            $request->optionDrafts(),
            $request->answer(),
        ));

        return new JsonResponse(['data' => $this->present($view)], $existed ? 200 : 201);
    }

    public function update(UpdateQuestionRequest $request, string $id, UpdateQuestionHandler $handler): JsonResponse
    {
        $view = $handler->handle(new UpdateQuestionCommand(
            $this->actors->fromRequest($request),
            $id,
            $request->integer('version'),
            (string) $request->string('statement'),
            $request->explanationText(),
            $request->optionDrafts(),
            $request->answer(),
        ));

        return new JsonResponse(['data' => $this->present($view)]);
    }

    public function deactivate(Request $request, string $id, SetQuestionActivationHandler $handler): JsonResponse
    {
        $view = $handler->handle(new SetQuestionActivationCommand($this->actors->fromRequest($request), $id, false));

        return new JsonResponse(['data' => $this->present($view)]);
    }

    public function reactivate(Request $request, string $id, SetQuestionActivationHandler $handler): JsonResponse
    {
        $view = $handler->handle(new SetQuestionActivationCommand($this->actors->fromRequest($request), $id, true));

        return new JsonResponse(['data' => $this->present($view)]);
    }

    /**
     * @return array{id: string, topic_id: string, type: string, statement: string, explanation: string|null,
     *     active: bool, version: int, options: list<array{id: string, text: string, correct: bool, position: int}>}
     */
    private function present(QuestionView $view): array
    {
        return [
            'id' => $view->id,
            'topic_id' => $view->topicId,
            'type' => $view->type,
            'statement' => $view->statement,
            'explanation' => $view->explanation,
            'active' => $view->active,
            'version' => $view->version,
            'options' => array_map(
                static fn (QuestionOptionView $o): array => ['id' => $o->id, 'text' => $o->text, 'correct' => $o->correct, 'position' => $o->position],
                $view->options,
            ),
        ];
    }
}
