<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Query\ListTopics;

final readonly class ListTopicsHandler
{
    public function __construct(private TopicListReader $reader) {}

    /** @return list<TopicListItem> */
    public function handle(ListTopicsQuery $query): array
    {
        return $this->reader->all();
    }
}
