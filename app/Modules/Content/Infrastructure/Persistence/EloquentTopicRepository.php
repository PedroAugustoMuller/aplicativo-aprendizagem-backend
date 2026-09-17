<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Persistence;

use App\Modules\Content\Domain\Entity\Topic;
use App\Modules\Content\Domain\Repository\TopicRepository;

final class EloquentTopicRepository implements TopicRepository
{
    public function __construct(private readonly TopicMapper $mapper) {}

    public function save(Topic $topic): void
    {
        TopicModel::query()->updateOrCreate(
            ['id' => $topic->id()->value()],
            $this->mapper->toAttributes($topic),
        );
    }
}
