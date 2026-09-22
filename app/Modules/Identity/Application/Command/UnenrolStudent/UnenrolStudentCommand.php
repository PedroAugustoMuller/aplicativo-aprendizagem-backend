<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Command\UnenrolStudent;

use App\Shared\Domain\Auth\Actor;

final readonly class UnenrolStudentCommand
{
    public function __construct(
        public Actor $actor,
        public string $classroomId,
        public string $studentId,
    ) {}
}
