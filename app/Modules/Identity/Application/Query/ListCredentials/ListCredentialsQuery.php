<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Query\ListCredentials;

use App\Shared\Domain\Auth\Actor;

final readonly class ListCredentialsQuery
{
    public function __construct(
        public Actor $actor,
        public string $classroomId,
    ) {}
}
