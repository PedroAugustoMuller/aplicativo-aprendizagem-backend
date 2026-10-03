<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Command\SetTopicActivation;

use App\Shared\Domain\Auth\Actor;

final readonly class SetTopicActivationCommand
{
    public function __construct(
        public Actor $actor,
        public string $id,
        public bool $active,
    ) {}
}
