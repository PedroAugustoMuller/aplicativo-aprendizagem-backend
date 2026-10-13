<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Command\ReplaceClassroomSubjects;

use App\Shared\Domain\Auth\Actor;

final readonly class ReplaceClassroomSubjectsCommand
{
    /** @param  list<string>  $subjectIds */
    public function __construct(
        public Actor $actor,
        public string $classroomId,
        public array $subjectIds,
    ) {}
}
