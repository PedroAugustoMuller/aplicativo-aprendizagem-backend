<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Command\SetTeacherActive;

use App\Shared\Domain\Auth\Actor;

final readonly class SetTeacherActiveCommand
{
    public function __construct(
        public Actor $actor,
        public string $id,
        public bool $active,
    ) {}
}
