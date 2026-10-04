<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Application\Query\GetClassroomProgress;

use App\Shared\Domain\Auth\Actor;

final readonly class GetClassroomProgressQuery
{
    public function __construct(
        public Actor $actor,
        public string $classroomId,
    ) {}
}
