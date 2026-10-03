<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Command\UpdateTopic;

use App\Shared\Domain\Auth\Actor;

/** A null field is left as it is (PATCH). */
final readonly class UpdateTopicCommand
{
    public function __construct(
        public Actor $actor,
        public string $id,
        public ?string $name,
        public ?string $description,
    ) {}
}
