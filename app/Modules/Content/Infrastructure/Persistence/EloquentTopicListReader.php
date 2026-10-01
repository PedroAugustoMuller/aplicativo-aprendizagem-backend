<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Persistence;

use App\Modules\Content\Application\Query\ListTopics\TopicListItem;
use App\Modules\Content\Application\Query\ListTopics\TopicListReader;
use App\Shared\Infrastructure\Persistence\EloquentAttribute;

/**
 * Reads straight into DTOs. Selecting only the displayed columns is the point:
 * hydrating aggregates to render a list is the cost this read model avoids.
 */
final class EloquentTopicListReader implements TopicListReader
{
    /** @return list<TopicListItem> */
    public function forSubject(string $subjectId): array
    {
        $items = [];

        foreach (TopicModel::query()
            ->select(['id', 'name', 'description', 'position'])
            ->where('subject_id', $subjectId)
            ->orderBy('position')
            ->get() as $model) {
            $items[] = new TopicListItem(
                id: EloquentAttribute::string($model->getKey(), 'topics.id'),
                name: EloquentAttribute::string($model->getAttribute('name'), 'topics.name'),
                description: EloquentAttribute::string($model->getAttribute('description'), 'topics.description'),
                // position skips EloquentAttribute: TopicModel casts it to integer and
                // declares it in its @property docblock, so Larastan already verifies
                // this access as int without help. id/name/description have no such
                // cast, so getAttribute() there stays mixed and needs the helper.
                position: $model->position,
            );
        }

        return $items;
    }
}
