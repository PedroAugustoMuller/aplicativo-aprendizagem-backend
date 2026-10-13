<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\DTO;

use App\Modules\Identity\Domain\Entity\Classroom;

final readonly class ClassroomView
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

    public static function of(Classroom $classroom): self
    {
        return new self(
            $classroom->id()->value(),
            $classroom->name()->value(),
            $classroom->subjectIds(),
            array_map(TeacherAssignmentView::of(...), $classroom->assignments()),
            count($classroom->studentIds()),
            $classroom->isActive(),
        );
    }
}
