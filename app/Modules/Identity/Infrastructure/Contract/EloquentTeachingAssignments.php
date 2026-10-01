<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Contract;

use App\Shared\Domain\Contract\TeachingAssignments;
use App\Shared\Infrastructure\Persistence\EloquentAttribute;
use Illuminate\Support\Facades\DB;

/**
 * Implements Shared's TeachingAssignments contract on top of Identity's own
 * classroom persistence, so other modules can ask what a user teaches or is
 * enrolled in without importing Identity's internals.
 */
final class EloquentTeachingAssignments implements TeachingAssignments
{
    public function subjectIdsTaughtBy(string $userId): array
    {
        return $this->subjectIds('classroom_teachers', $userId);
    }

    public function subjectIdsEnrolledBy(string $userId): array
    {
        return $this->subjectIds('classroom_students', $userId);
    }

    /** @return list<string> */
    private function subjectIds(string $pivot, string $userId): array
    {
        /** @var list<string> $ids */
        $ids = DB::table('classrooms')
            ->join($pivot, $pivot.'.classroom_id', '=', 'classrooms.id')
            ->where($pivot.'.user_id', $userId)
            ->whereNull('classrooms.deactivated_at')
            ->distinct()
            ->pluck('classrooms.subject_id')
            ->map(fn (mixed $id): string => EloquentAttribute::string($id, 'classrooms.subject_id'))
            ->values()
            ->all();

        return $ids;
    }
}
