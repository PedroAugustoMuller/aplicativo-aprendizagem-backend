<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Command\CreateStudents;

use App\Shared\Domain\Auth\Actor;

final readonly class CreateStudentsCommand
{
    /** @param  list<array{id: string, name: string}>  $students */
    public function __construct(
        public Actor $actor,
        public string $classroomId,
        public array $students,
    ) {}
}
