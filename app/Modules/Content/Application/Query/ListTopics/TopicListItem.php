<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Query\ListTopics;

/**
 * A read model. Not the Topic aggregate — reading a list must not hydrate aggregates.
 */
final readonly class TopicListItem
{
    public function __construct(
        public string $id,
        public string $name,
        public string $description,
        public int $position,
    ) {}
}
