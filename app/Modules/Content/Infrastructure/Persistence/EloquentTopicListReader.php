<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Persistence;

use App\Modules\Content\Application\Query\ListTopics\TopicListItem;
use App\Modules\Content\Application\Query\ListTopics\TopicListReader;
use App\Shared\Infrastructure\Persistence\EloquentAttribute;
use Illuminate\Support\Facades\DB;

/**
 * Reads straight into DTOs. Selecting only the displayed columns is the point:
 * hydrating aggregates to render a list is the cost this read model avoids.
 */
final class EloquentTopicListReader implements TopicListReader
{
    /** @return list<TopicListItem> */
    public function forSubject(string $subjectId, bool $includeInactive): array
    {
        $query = TopicModel::query()
            ->select(['topics.id', 'topics.name', 'topics.description', 'topics.position', 'topics.deactivated_at'])
            ->selectSub(
                DB::table('questions')
                    ->selectRaw('count(*)')
                    ->whereColumn('questions.topic_id', 'topics.id')
                    ->whereNull('questions.deactivated_at'),
                'active_question_count',
            )
            ->where('topics.subject_id', $subjectId)
            ->orderBy('topics.position')
            ->orderBy('topics.name');

        if (! $includeInactive) {
            $query->whereNull('topics.deactivated_at');
        }

        $items = [];

        foreach ($query->get() as $model) {
            $count = $model->getAttribute('active_question_count');

            $items[] = new TopicListItem(
                id: EloquentAttribute::string($model->getKey(), 'topics.id'),
                name: EloquentAttribute::string($model->getAttribute('name'), 'topics.name'),
                description: EloquentAttribute::string($model->getAttribute('description'), 'topics.description'),
                // position is cast to int by TopicModel; see its @property docblock.
                position: $model->position,
                active: $model->getAttribute('deactivated_at') === null,
                activeQuestionCount: is_numeric($count) ? (int) $count : 0,
            );
        }

        return $items;
    }
}
