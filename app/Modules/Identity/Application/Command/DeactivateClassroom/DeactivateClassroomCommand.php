<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Command\DeactivateClassroom;

use App\Shared\Domain\Auth\Actor;

final readonly class DeactivateClassroomCommand
{
    public function __construct(
        public Actor $actor,
        public string $id,
    ) {}
}
