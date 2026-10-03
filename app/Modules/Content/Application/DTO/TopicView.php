<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\DTO;

use App\Modules\Content\Domain\Entity\Topic;

final readonly class TopicView
{
    public function __construct(
        public string $id,
        public string $subjectId,
        public string $name,
        public string $description,
        public int $position,
        public bool $active,
    ) {}

    public static function of(Topic $topic): self
    {
        return new self(
            $topic->id()->value(),
            $topic->subjectId()->value(),
            $topic->name()->value(),
            $topic->description(),
            $topic->position(),
            $topic->isActive(),
        );
    }
}
