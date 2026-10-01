<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Command\AssignTeachers;

use App\Shared\Domain\Auth\Actor;

final readonly class AssignTeachersCommand
{
    /** @param  list<string>  $teacherIds */
    public function __construct(
        public Actor $actor,
        public string $classroomId,
        public array $teacherIds,
    ) {}
}
