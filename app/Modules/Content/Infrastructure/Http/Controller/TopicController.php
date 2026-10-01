<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Http\Controller;

use App\Modules\Content\Application\Query\ListTopics\ListTopicsHandler;
use App\Modules\Content\Application\Query\ListTopics\ListTopicsQuery;
use App\Modules\Content\Application\Query\ListTopics\TopicListItem;
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
}
