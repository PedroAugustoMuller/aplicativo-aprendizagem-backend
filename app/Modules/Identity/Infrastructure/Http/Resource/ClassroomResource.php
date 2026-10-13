<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Http\Resource;

use App\Modules\Identity\Application\DTO\ClassroomView;
use App\Modules\Identity\Application\DTO\TeacherAssignmentView;
use App\Modules\Identity\Application\Query\ListClassrooms\ClassroomListItem;

/** The one place a classroom is formatted for the API, for both the list and every write. */
final class ClassroomResource
{
    /**
     * Students have no use for staff ids: their list hides who teaches.
     *
     * @return array{id: string, name: string, subject_ids: list<string>, teachers: list<array{teacher_id: string, subject_id: ?string}>, student_count: int, active: bool}
     */
    public static function toArray(ClassroomView|ClassroomListItem $classroom, bool $hideTeachers = false): array
    {
        return [
            'id' => $classroom->id,
            'name' => $classroom->name,
            'subject_ids' => $classroom->subjectIds,
            'teachers' => $hideTeachers ? [] : array_map(
                static fn (TeacherAssignmentView $t): array => ['teacher_id' => $t->teacherId, 'subject_id' => $t->subjectId],
                $classroom->teachers,
            ),
            'student_count' => $classroom->studentCount,
            'active' => $classroom->active,
        ];
    }
}
