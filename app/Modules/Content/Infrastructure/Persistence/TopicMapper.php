<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Persistence;

use App\Modules\Content\Domain\Entity\Topic;
use App\Modules\Content\Domain\ValueObject\SubjectId;
use App\Modules\Content\Domain\ValueObject\TopicId;
use App\Modules\Content\Domain\ValueObject\TopicName;
use App\Shared\Infrastructure\Persistence\EloquentAttribute;
use DateTimeImmutable;

final class TopicMapper
{
    public function toDomain(TopicModel $model): Topic
    {
        $deactivatedAt = $model->getAttribute('deactivated_at');

        return Topic::restore(
            new TopicId(EloquentAttribute::string($model->getKey(), 'topics.id')),
            new SubjectId(EloquentAttribute::string($model->getAttribute('subject_id'), 'topics.subject_id')),
            new TopicName(EloquentAttribute::string($model->getAttribute('name'), 'topics.name')),
            EloquentAttribute::string($model->getAttribute('description'), 'topics.description'),
            $model->position,
            $deactivatedAt instanceof DateTimeImmutable ? $deactivatedAt : null,
        );
    }

    /**
     * @return array<string, int|string|DateTimeImmutable|null>
     */
    public function toAttributes(Topic $topic): array
    {
        return [
            'subject_id' => $topic->subjectId()->value(),
            'name' => $topic->name()->value(),
            'description' => $topic->description(),
            'position' => $topic->position(),
            'deactivated_at' => $topic->deactivatedAt(),
        ];
    }
}
