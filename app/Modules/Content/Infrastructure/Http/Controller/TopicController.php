<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Http\Controller;

use App\Modules\Content\Application\Query\ListTopics\ListTopicsHandler;
use App\Modules\Content\Application\Query\ListTopics\ListTopicsQuery;
use App\Modules\Content\Application\Query\ListTopics\TopicListItem;
use Illuminate\Http\JsonResponse;

final class TopicController
{
    public function index(ListTopicsHandler $handler): JsonResponse
    {
        $items = $handler->handle(new ListTopicsQuery);

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
