<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Contract;

use App\Shared\Domain\Contract\ClassroomRoster;
use App\Shared\Domain\Contract\RosterClassroom;
use App\Shared\Domain\Contract\RosterStudent;
use App\Shared\Infrastructure\Persistence\EloquentAttribute;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Implements Shared's ClassroomRoster on Identity's own tables, so Quiz never imports Identity. */
final class EloquentClassroomRoster implements ClassroomRoster
{
    public function find(string $classroomId): ?RosterClassroom
    {
        return $this->load(DB::table('classrooms')->where('id', $classroomId))[0] ?? null;
    }

    public function forSubject(string $subjectId): array
    {
        return $this->load(DB::table('classrooms')->where('subject_id', $subjectId)->orderBy('name')->orderBy('id'));
    }

    /**
     * Three queries however many classrooms: the classrooms, their teachers, their students.
     *
     * @return list<RosterClassroom>
     */
    private function load(Builder $classrooms): array
    {
        $rows = $classrooms->get(['id', 'subject_id', 'deactivated_at']);

        if ($rows->isEmpty()) {
            return [];
        }

        $ids = $rows->map(static fn (object $row): string => EloquentAttribute::string($row->id ?? null, 'classrooms.id'))->all();
        $teacherIds = [];

        foreach (DB::table('classroom_teachers')->whereIn('classroom_id', $ids)->orderBy('user_id')->get(['classroom_id', 'user_id']) as $row) {
            $teacherIds[EloquentAttribute::string($row->classroom_id, 'classroom_teachers.classroom_id')][] = EloquentAttribute::string($row->user_id, 'classroom_teachers.user_id');
        }

        $students = [];

        $enrolled = DB::table('classroom_students')
            ->join('users', 'users.id', '=', 'classroom_students.user_id')
            ->whereIn('classroom_students.classroom_id', $ids)
            ->orderBy('users.name')
            ->get(['classroom_students.classroom_id', 'users.id', 'users.name', 'users.username']);

        foreach ($enrolled as $row) {
            $students[EloquentAttribute::string($row->classroom_id, 'classroom_students.classroom_id')][] = new RosterStudent(
                EloquentAttribute::string($row->id, 'users.id'),
                EloquentAttribute::string($row->name, 'users.name'),
                EloquentAttribute::string($row->username, 'users.username'),
            );
        }

        return array_values($rows->map(static function (object $row) use ($teacherIds, $students): RosterClassroom {
            $id = EloquentAttribute::string($row->id ?? null, 'classrooms.id');

            return new RosterClassroom(
                $id,
                EloquentAttribute::string($row->subject_id ?? null, 'classrooms.subject_id'),
                ($row->deactivated_at ?? null) === null,
                $teacherIds[$id] ?? [],
                $students[$id] ?? [],
            );
        })->all());
    }
}
