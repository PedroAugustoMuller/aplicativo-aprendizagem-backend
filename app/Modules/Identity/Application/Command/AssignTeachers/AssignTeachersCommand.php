<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Command\AssignTeachers;

use App\Shared\Domain\Auth\Actor;

final readonly class AssignTeachersCommand
{
    /** @param  list<array{teacherId: string, subjectId: ?string}>  $assignments  subjectId null: the whole classroom */
    public function __construct(
        public Actor $actor,
        public string $classroomId,
        public array $assignments,
    ) {}
}
