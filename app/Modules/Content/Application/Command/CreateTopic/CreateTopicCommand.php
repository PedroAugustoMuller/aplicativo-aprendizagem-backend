<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Command\CreateTopic;

use App\Shared\Domain\Auth\Actor;

final readonly class CreateTopicCommand
{
    public function __construct(
        public Actor $actor,
        public string $subjectId,
        public string $id,
        public string $name,
        public string $description,
    ) {}
}
