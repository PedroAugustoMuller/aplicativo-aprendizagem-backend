<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Persistence;

use App\Modules\Identity\Domain\Entity\Classroom;
use App\Modules\Identity\Domain\Repository\ClassroomRepository;
use App\Modules\Identity\Domain\ValueObject\ClassroomId;
use App\Modules\Identity\Domain\ValueObject\ClassroomName;
use App\Modules\Identity\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\EloquentAttribute;
use Illuminate\Support\Facades\DB;

final class EloquentClassroomRepository implements ClassroomRepository
{
    public function __construct(private readonly ClassroomMapper $mapper) {}

    public function findById(ClassroomId $id): ?Classroom
    {
        $model = ClassroomModel::query()->find($id->value());

        if (! $model instanceof ClassroomModel) {
            return null;
        }

        return $this->mapper->toDomain($model, $this->pivotUserIds('classroom_teachers', $id), $this->pivotUserIds('classroom_students', $id));
    }

    /**
     * Case-insensitive on purpose: the DB unique index on `name` stays case-sensitive
     * as a last line of defence, this check is the actual business rule.
     */
    public function nameTakenByAnother(ClassroomName $name, ClassroomId $except): bool
    {
        return ClassroomModel::query()
            ->whereRaw('lower(name) = ?', [mb_strtolower($name->value())])
            ->whereKeyNot($except->value())
            ->exists();
    }

    /**
     * Loads every matching classroom plus both pivots in three queries total,
     * regardless of how many classrooms the student is in — findById() in a loop
     * would instead cost one query per classroom (an N+1 once a caller such as
     * RosterPolicy::canManageStudent runs this on every request).
     */
    public function findByStudent(UserId $studentId): array
    {
        $models = ClassroomModel::query()
            ->join('classroom_students', 'classroom_students.classroom_id', '=', 'classrooms.id')
            ->where('classroom_students.user_id', $studentId->value())
            ->select('classrooms.*')
            ->get();

        if ($models->isEmpty()) {
            return [];
        }

        $classroomIds = array_values($models
            ->map(fn (ClassroomModel $model): string => EloquentAttribute::string($model->getKey(), 'classrooms.id'))
            ->all());

        $teachersByClassroom = $this->pivotUserIdsGroupedByClassroom('classroom_teachers', $classroomIds);
        $studentsByClassroom = $this->pivotUserIdsGroupedByClassroom('classroom_students', $classroomIds);

        return array_values($models
            ->map(function (ClassroomModel $model) use ($teachersByClassroom, $studentsByClassroom): Classroom {
                $id = EloquentAttribute::string($model->getKey(), 'classrooms.id');

                return $this->mapper->toDomain($model, $teachersByClassroom[$id] ?? [], $studentsByClassroom[$id] ?? []);
            })
            ->all());
    }

    public function save(Classroom $classroom): void
    {
        DB::transaction(function () use ($classroom): void {
            ClassroomModel::query()->updateOrCreate(
                ['id' => $classroom->id()->value()],
                $this->mapper->toAttributes($classroom),
            );

            $this->syncPivot('classroom_teachers', $classroom->id()->value(), array_map(
                static fn (UserId $id): string => $id->value(),
                $classroom->teacherIds(),
            ));

            $this->syncPivot('classroom_students', $classroom->id()->value(), array_map(
                static fn (UserId $id): string => $id->value(),
                $classroom->studentIds(),
            ));
        });
    }

    /** @param  list<string>  $userIds */
    private function syncPivot(string $table, string $classroomId, array $userIds): void
    {
        DB::table($table)
            ->where('classroom_id', $classroomId)
            ->when($userIds !== [], fn ($query) => $query->whereNotIn('user_id', $userIds))
            ->delete();

        if ($userIds === []) {
            return;
        }

        DB::table($table)->insertOrIgnore(array_map(
            static fn (string $userId): array => ['classroom_id' => $classroomId, 'user_id' => $userId],
            $userIds,
        ));
    }

    /** @return list<string> */
    private function pivotUserIds(string $table, ClassroomId $classroomId): array
    {
        return array_values(DB::table($table)
            ->where('classroom_id', $classroomId->value())
            ->pluck('user_id')
            ->map(fn (mixed $id): string => EloquentAttribute::string($id, $table.'.user_id'))
            ->all());
    }

    /**
     * @param  list<string>  $classroomIds
     * @return array<string, list<string>> classroomId => userIds
     */
    private function pivotUserIdsGroupedByClassroom(string $table, array $classroomIds): array
    {
        $grouped = [];
        foreach (DB::table($table)->whereIn('classroom_id', $classroomIds)->get(['classroom_id', 'user_id']) as $row) {
            $classroomId = EloquentAttribute::string($row->classroom_id, $table.'.classroom_id');
            $grouped[$classroomId][] = EloquentAttribute::string($row->user_id, $table.'.user_id');
        }

        return $grouped;
    }
}
