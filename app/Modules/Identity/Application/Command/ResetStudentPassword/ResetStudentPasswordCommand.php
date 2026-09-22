<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Command\ResetStudentPassword;

use App\Shared\Domain\Auth\Actor;

final readonly class ResetStudentPasswordCommand
{
    public function __construct(
        public Actor $actor,
        public string $studentId,
    ) {}
}
