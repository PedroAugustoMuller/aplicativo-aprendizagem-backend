<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Command\SetStudentActive;

use App\Shared\Domain\Auth\Actor;

final readonly class SetStudentActiveCommand
{
    public function __construct(
        public Actor $actor,
        public string $studentId,
        public bool $active,
    ) {}
}
