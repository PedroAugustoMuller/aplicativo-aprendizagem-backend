<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Application\Query\GetStudentAttempt;

use App\Shared\Domain\Auth\Actor;

final readonly class GetStudentAttemptQuery
{
    public function __construct(
        public Actor $actor,
        public string $classroomId,
        public string $studentId,
        public string $attemptId,
    ) {}
}
