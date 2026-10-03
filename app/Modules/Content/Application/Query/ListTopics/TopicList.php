<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Query\ListTopics;

final readonly class TopicList
{
    /** @param list<TopicListItem> $items */
    public function __construct(
        public bool $canAuthor,
        public array $items,
    ) {}
}
