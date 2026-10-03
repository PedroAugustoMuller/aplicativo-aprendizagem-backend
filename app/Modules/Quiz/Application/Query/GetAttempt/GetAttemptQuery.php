<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Application\Query\GetAttempt;

use App\Shared\Domain\Auth\Actor;

final readonly class GetAttemptQuery
{
    public function __construct(
        public Actor $actor,
        public string $attemptId,
    ) {}
}
