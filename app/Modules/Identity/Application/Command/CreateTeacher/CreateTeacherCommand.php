<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Command\CreateTeacher;

use App\Shared\Domain\Auth\Actor;

final readonly class CreateTeacherCommand
{
    public function __construct(
        public Actor $actor,
        public string $id,
        public string $name,
        public string $email,
    ) {}
}
