<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\Entity;

use App\Modules\Content\Domain\ValueObject\TopicId;
use App\Modules\Content\Domain\ValueObject\TopicName;
use InvalidArgumentException;

final class Topic
{
    public function __construct(
        private readonly TopicId $id,
        private readonly TopicName $name,
        private readonly string $description,
        private readonly int $position,
    ) {
        if ($position < 0) {
            throw new InvalidArgumentException('Topic position cannot be negative.');
        }
    }

    public function id(): TopicId
    {
        return $this->id;
    }

    public function name(): TopicName
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
    }

    /** Ordering within the syllabus. Lower comes first. */
    public function position(): int
    {
        return $this->position;
    }
}
