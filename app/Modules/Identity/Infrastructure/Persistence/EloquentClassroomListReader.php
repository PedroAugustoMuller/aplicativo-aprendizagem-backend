<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Persistence;

use App\Modules\Identity\Application\Query\ListClassrooms\ClassroomListItem;
use App\Modules\Identity\Application\Query\ListClassrooms\ClassroomListReader;
use App\Shared\Infrastructure\Persistence\EloquentAttribute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Reads straight into DTOs. Selecting only the displayed columns is the point:
 * hydrating aggregates to render a list is the cost this read model avoids.
 */
final class EloquentClassroomListReader implements ClassroomListReader
{
    public function all(): array
    {
        return $this->list(fn (Builder $query): Builder => $query);
    }

    public function taughtBy(string $userId): array
    {
        return $this->list(fn (Builder $query): Builder => $query->whereExists(
            fn ($sub) => $sub->selectRaw('1')
                ->from('classroom_teachers')
                ->whereColumn('classroom_teachers.classroom_id', 'classrooms.id')
                ->where('classroom_teachers.user_id', $userId),
        ));
    }

    public function enrolledBy(string $userId): array
    {
        return $this->list(fn (Builder $query): Builder => $query->whereNull('deactivated_at')->whereExists(
            fn ($sub) => $sub->selectRaw('1')
                ->from('classroom_students')
                ->whereColumn('classroom_students.classroom_id', 'classrooms.id')
                ->where('classroom_students.user_id', $userId),
        ));
    }

    /**
     * @param  callable(Builder<ClassroomModel>): Builder<ClassroomModel>  $scope
     * @return list<ClassroomListItem>
     */
    private function list(callable $scope): array
    {
        $query = $scope(ClassroomModel::query()
            ->select(['classrooms.id', 'classrooms.name', 'classrooms.subject_id', 'classrooms.deactivated_at'])
            ->selectSub(
                DB::table('classroom_students')
                    ->selectRaw('count(*)')
                    ->whereColumn('classroom_students.classroom_id', 'classrooms.id'),
                'student_count',
            )
            ->orderBy('classrooms.name'));

        $models = $query->get();

        $teacherIdsByClassroom = $this->teacherIdsByClassroom(array_values($models->pluck('id')->all()));

        $items = [];
        foreach ($models as $model) {
            $id = EloquentAttribute::string($model->getKey(), 'classrooms.id');

            $items[] = new ClassroomListItem(
                id: $id,
                name: EloquentAttribute::string($model->getAttribute('name'), 'classrooms.name'),
                subjectId: EloquentAttribute::string($model->getAttribute('subject_id'), 'classrooms.subject_id'),
                teacherIds: $teacherIdsByClassroom[$id] ?? [],
                studentCount: EloquentAttribute::int($model->getAttribute('student_count'), 'classrooms.student_count'),
                active: $model->getAttribute('deactivated_at') === null,
            );
        }

        return $items;
    }

    /**
     * @param  list<mixed>  $classroomIds
     * @return array<string, list<string>>
     */
    private function teacherIdsByClassroom(array $classroomIds): array
    {
        if ($classroomIds === []) {
            return [];
        }

        $rows = DB::table('classroom_teachers')
            ->whereIn('classroom_id', $classroomIds)
            ->get(['classroom_id', 'user_id']);

        $grouped = [];
        foreach ($rows->groupBy('classroom_id') as $classroomId => $teachersOfClassroom) {
            $grouped[(string) $classroomId] = array_values($teachersOfClassroom
                ->pluck('user_id')
                ->map(fn (mixed $userId): string => EloquentAttribute::string($userId, 'classroom_teachers.user_id'))
                ->all());
        }

        return $grouped;
    }
}
