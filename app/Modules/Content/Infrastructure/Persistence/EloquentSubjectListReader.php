<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Persistence;

use App\Modules\Content\Application\Query\ListSubjects\SubjectListItem;
use App\Modules\Content\Application\Query\ListSubjects\SubjectListReader;
use App\Shared\Infrastructure\Persistence\EloquentAttribute;

/**
 * Reads straight into DTOs. Selecting only the displayed columns is the point:
 * hydrating aggregates to render a list is the cost this read model avoids.
 */
final class EloquentSubjectListReader implements SubjectListReader
{
    /** @return list<SubjectListItem> */
    public function list(?array $onlyIds): array
    {
        $query = SubjectModel::query()
            ->select(['id', 'name', 'deactivated_at'])
            ->orderBy('name');

        if ($onlyIds !== null) {
            $query->whereIn('id', $onlyIds);
        }

        $items = [];

        foreach ($query->get() as $model) {
            $items[] = new SubjectListItem(
                id: EloquentAttribute::string($model->getKey(), 'subjects.id'),
                name: EloquentAttribute::string($model->getAttribute('name'), 'subjects.name'),
                active: $model->getAttribute('deactivated_at') === null,
            );
        }

        return $items;
    }
}
