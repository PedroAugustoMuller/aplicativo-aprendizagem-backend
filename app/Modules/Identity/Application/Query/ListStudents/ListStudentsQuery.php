<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Query\ListStudents;

use App\Shared\Domain\Auth\Actor;

final readonly class ListStudentsQuery
{
    public function __construct(
        public Actor $actor,
        public string $classroomId,
    ) {}
}
