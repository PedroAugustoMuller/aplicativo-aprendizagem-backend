<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Query\ListClassrooms;

use App\Modules\Identity\Application\DTO\TeacherAssignmentView;

final readonly class ClassroomListItem
{
    /**
     * @param  list<string>  $subjectIds
     * @param  list<TeacherAssignmentView>  $teachers
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $subjectIds,
        public array $teachers,
        public int $studentCount,
        public bool $active,
    ) {}
}
