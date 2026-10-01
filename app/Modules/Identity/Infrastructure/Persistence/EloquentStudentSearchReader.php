<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Persistence;

use App\Modules\Identity\Application\Query\SearchStudents\StudentSearchReader;
use App\Modules\Identity\Application\Query\SearchStudents\StudentSearchResult;
use App\Shared\Domain\Auth\Role;
use App\Shared\Infrastructure\Persistence\EloquentAttribute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/** Reads straight into DTOs: two narrow queries, no aggregate hydration. */
final class EloquentStudentSearchReader implements StudentSearchReader
{
    public function search(string $text, int $limit): array
    {
        // Backslash is PostgreSQL's default LIKE escape: "%" and "_" typed by a
        // teacher are literal characters, not wildcards.
        $pattern = '%'.addcslashes($text, '\\%_').'%';

        $models = UserModel::query()
            ->select(['id', 'name', 'username'])
            ->where('role', Role::Student->value)
            ->whereNull('deactivated_at')
            ->where(function (Builder $query) use ($pattern): void {
                $query->where('name', 'ilike', $pattern)->orWhere('username', 'ilike', $pattern);
            })
            ->orderBy('name')
            ->limit($limit)
            ->get();

        $ids = [];
        foreach ($models as $model) {
            $ids[] = EloquentAttribute::string($model->getKey(), 'users.id');
        }

        $classrooms = $this->activeClassroomsOf($ids);

        $results = [];
        foreach ($models as $model) {
            $id = EloquentAttribute::string($model->getKey(), 'users.id');
            $results[] = new StudentSearchResult(
                id: $id,
                name: EloquentAttribute::string($model->getAttribute('name'), 'users.name'),
                login: EloquentAttribute::string($model->getAttribute('username'), 'users.username'),
                classrooms: $classrooms[$id] ?? [],
            );
        }

        return $results;
    }

    /**
     * @param  list<string>  $studentIds
     * @return array<string, list<array{id: string, name: string}>>
     */
    private function activeClassroomsOf(array $studentIds): array
    {
        if ($studentIds === []) {
            return [];
        }

        $rows = DB::table('classroom_students')
            ->join('classrooms', 'classrooms.id', '=', 'classroom_students.classroom_id')
            ->whereIn('classroom_students.user_id', $studentIds)
            ->whereNull('classrooms.deactivated_at')
            ->orderBy('classrooms.name')
            ->get(['classroom_students.user_id as student_id', 'classrooms.id as classroom_id', 'classrooms.name as classroom_name']);

        $byStudent = [];
        foreach ($rows as $row) {
            $studentId = EloquentAttribute::string($row->student_id, 'classroom_students.user_id');
            $byStudent[$studentId][] = [
                'id' => EloquentAttribute::string($row->classroom_id, 'classrooms.id'),
                'name' => EloquentAttribute::string($row->classroom_name, 'classrooms.name'),
            ];
        }

        return $byStudent;
    }
}
