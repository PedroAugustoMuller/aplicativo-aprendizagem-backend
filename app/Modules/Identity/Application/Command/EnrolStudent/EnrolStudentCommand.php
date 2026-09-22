<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Command\EnrolStudent;

use App\Shared\Domain\Auth\Actor;

final readonly class EnrolStudentCommand
{
    public function __construct(
        public Actor $actor,
        public string $classroomId,
        public string $studentId,
    ) {}
}
