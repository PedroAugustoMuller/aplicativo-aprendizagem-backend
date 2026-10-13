<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Command\CreateClassroom;

use App\Shared\Domain\Auth\Actor;

final readonly class CreateClassroomCommand
{
    /** @param  list<string>  $subjectIds */
    public function __construct(
        public Actor $actor,
        public string $id,
        public string $name,
        public array $subjectIds,
    ) {}
}
