<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Command\ResetTeacherPassword;

use App\Shared\Domain\Auth\Actor;

final readonly class ResetTeacherPasswordCommand
{
    public function __construct(
        public Actor $actor,
        public string $id,
    ) {}
}
