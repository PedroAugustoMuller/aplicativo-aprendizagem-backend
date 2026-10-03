<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Application\Command\StartAttempt;

use App\Shared\Domain\Auth\Actor;

final readonly class StartAttemptCommand
{
    public function __construct(
        public Actor $actor,
        public string $topicId,
        public string $attemptId,
    ) {}
}
