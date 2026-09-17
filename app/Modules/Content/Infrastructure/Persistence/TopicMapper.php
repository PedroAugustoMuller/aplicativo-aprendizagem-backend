<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Persistence;

use App\Modules\Content\Domain\Entity\Topic;

/**
 * Write side only. Content has no read-by-id path yet, so there is deliberately no
 * toDomain() here — Identity's UserMapper demonstrates the Eloquent-to-domain
 * direction with a real caller. Add it here when something actually reads a topic
 * through the aggregate.
 */
final class TopicMapper
{
    /**
     * @return array<string, int|string>
     */
    public function toAttributes(Topic $topic): array
    {
        return [
            'name' => $topic->name()->value(),
            'description' => $topic->description(),
            'position' => $topic->position(),
        ];
    }
}
