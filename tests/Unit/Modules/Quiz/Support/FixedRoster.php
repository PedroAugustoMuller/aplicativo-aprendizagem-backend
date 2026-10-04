<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Quiz\Support;

use App\Shared\Domain\Contract\ClassroomRoster;
use App\Shared\Domain\Contract\RosterClassroom;

final class FixedRoster implements ClassroomRoster
{
    /** @param list<RosterClassroom> $classrooms */
    public function __construct(private readonly array $classrooms) {}

    public function find(string $classroomId): ?RosterClassroom
    {
        foreach ($this->classrooms as $classroom) {
            if ($classroom->id === $classroomId) {
                return $classroom;
            }
        }

        return null;
    }
}
