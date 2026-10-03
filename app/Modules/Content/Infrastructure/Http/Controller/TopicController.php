<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Http\Controller;

use App\Modules\Content\Application\Command\CreateTopic\CreateTopicCommand;
use App\Modules\Content\Application\Command\CreateTopic\CreateTopicHandler;
use App\Modules\Content\Application\Command\SetTopicActivation\SetTopicActivationCommand;
use App\Modules\Content\Application\Command\SetTopicActivation\SetTopicActivationHandler;
use App\Modules\Content\Application\Command\UpdateTopic\UpdateTopicCommand;
use App\Modules\Content\Application\Command\UpdateTopic\UpdateTopicHandler;
use App\Modules\Content\Application\DTO\TopicView;
use App\Modules\Content\Application\Query\ListTopics\ListTopicsHandler;
use App\Modules\Content\Application\Query\ListTopics\ListTopicsQuery;
use App\Modules\Content\Application\Query\ListTopics\TopicListItem;
use App\Modules\Content\Domain\Repository\TopicRepository;
use App\Modules\Content\Domain\ValueObject\TopicId;
use App\Modules\Content\Infrastructure\Http\Request\CreateTopicRequest;
use App\Modules\Content\Infrastructure\Http\Request\UpdateTopicRequest;
use App\Shared\Infrastructure\Http\ActorFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TopicController
{
    public function __construct(private readonly ActorFactory $actors) {}

    public function index(Request $request, string $id, ListTopicsHandler $handler): JsonResponse
    {
        $items = $handler->handle(new ListTopicsQuery($this->actors->fromRequest($request), $id));

        return new JsonResponse([
            'data' => array_map(
                fn (TopicListItem $item): array => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'description' => $item->description,
                    'position' => $item->position,
                ],
                $items,
            ),
        ]);
    }

    public function store(CreateTopicRequest $request, string $id, CreateTopicHandler $handler, TopicRepository $topics): JsonResponse
    {
        $existed = $topics->findById(new TopicId((string) $request->string('id'))) !== null;

        $view = $handler->handle(new CreateTopicCommand(
            $this->actors->fromRequest($request),
            $id,
            (string) $request->string('id'),
            (string) $request->string('name'),
            (string) $request->string('description'),
        ));

        return new JsonResponse(['data' => $this->present($view)], $existed ? 200 : 201);
    }

    public function update(UpdateTopicRequest $request, string $id, UpdateTopicHandler $handler): JsonResponse
    {
        $view = $handler->handle(new UpdateTopicCommand(
            $this->actors->fromRequest($request),
            $id,
            $request->has('name') ? (string) $request->string('name') : null,
            $request->has('description') ? (string) $request->string('description') : null,
        ));

        return new JsonResponse(['data' => $this->present($view)]);
    }

    public function deactivate(Request $request, string $id, SetTopicActivationHandler $handler): JsonResponse
    {
        return $this->setActive($request, $id, false, $handler);
    }

    public function reactivate(Request $request, string $id, SetTopicActivationHandler $handler): JsonResponse
    {
        return $this->setActive($request, $id, true, $handler);
    }

    private function setActive(Request $request, string $id, bool $active, SetTopicActivationHandler $handler): JsonResponse
    {
        $view = $handler->handle(new SetTopicActivationCommand($this->actors->fromRequest($request), $id, $active));

        return new JsonResponse(['data' => $this->present($view)]);
    }

    /** @return array{id: string, subject_id: string, name: string, description: string, position: int, active: bool} */
    private function present(TopicView $view): array
    {
        return [
            'id' => $view->id,
            'subject_id' => $view->subjectId,
            'name' => $view->name,
            'description' => $view->description,
            'position' => $view->position,
            'active' => $view->active,
        ];
    }
}
