<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Query\ListClassrooms;

final readonly class ClassroomListItem
{
    /** @param  list<string>  $teacherIds */
    public function __construct(
        public string $id,
        public string $name,
        public string $subjectId,
        public array $teacherIds,
        public int $studentCount,
        public bool $active,
    ) {}
}
