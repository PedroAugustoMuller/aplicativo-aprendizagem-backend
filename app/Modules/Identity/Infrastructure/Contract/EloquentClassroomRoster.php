<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Contract;

use App\Shared\Domain\Contract\ClassroomRoster;
use App\Shared\Domain\Contract\RosterClassroom;
use App\Shared\Domain\Contract\RosterStudent;
use App\Shared\Infrastructure\Persistence\EloquentAttribute;
use Illuminate\Support\Facades\DB;

/** Implements Shared's ClassroomRoster on Identity's own tables, so Quiz never imports Identity. */
final class EloquentClassroomRoster implements ClassroomRoster
{
    public function find(string $classroomId): ?RosterClassroom
    {
        $classroom = DB::table('classrooms')->where('id', $classroomId)->first(['id', 'subject_id', 'deactivated_at']);

        if ($classroom === null) {
            return null;
        }

        $teacherIds = [];

        foreach (DB::table('classroom_teachers')->where('classroom_id', $classroomId)->pluck('user_id') as $id) {
            $teacherIds[] = EloquentAttribute::string($id, 'classroom_teachers.user_id');
        }

        $students = [];

        $rows = DB::table('classroom_students')
            ->join('users', 'users.id', '=', 'classroom_students.user_id')
            ->where('classroom_students.classroom_id', $classroomId)
            ->orderBy('users.name')
            ->get(['users.id', 'users.name', 'users.username']);

        foreach ($rows as $row) {
            $students[] = new RosterStudent(
                EloquentAttribute::string($row->id, 'users.id'),
                EloquentAttribute::string($row->name, 'users.name'),
                EloquentAttribute::string($row->username, 'users.username'),
            );
        }

        return new RosterClassroom(
            EloquentAttribute::string($classroom->id, 'classrooms.id'),
            EloquentAttribute::string($classroom->subject_id, 'classrooms.subject_id'),
            $classroom->deactivated_at === null,
            $teacherIds,
            $students,
        );
    }
}
