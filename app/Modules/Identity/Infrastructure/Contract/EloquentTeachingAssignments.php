<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Contract;

use App\Shared\Domain\Contract\TeachingAssignments;
use App\Shared\Infrastructure\Persistence\EloquentAttribute;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Implements Shared's TeachingAssignments contract on top of Identity's own
 * classroom persistence, so other modules can ask what a user teaches or is
 * enrolled in without importing Identity's internals.
 */
final class EloquentTeachingAssignments implements TeachingAssignments
{
    /** Every subject of a whole-classroom assignment, plus each subject assignment. */
    public function subjectIdsTaughtBy(string $userId): array
    {
        $whole = DB::table('classroom_teachers as t')
            ->join('classrooms as c', 'c.id', '=', 't.classroom_id')
            ->join('classroom_subjects as s', 's.classroom_id', '=', 't.classroom_id')
            ->where('t.user_id', $userId)
            ->whereNull('t.subject_id')
            ->whereNull('c.deactivated_at')
            ->select('s.subject_id');

        $specific = DB::table('classroom_teachers as t')
            ->join('classrooms as c', 'c.id', '=', 't.classroom_id')
            ->join('classroom_subjects as s', static fn ($join) => $join->on('s.classroom_id', '=', 't.classroom_id')->on('s.subject_id', '=', 't.subject_id'))
            ->where('t.user_id', $userId)
            ->whereNull('c.deactivated_at')
            ->select('s.subject_id');

        return $this->ids($whole->union($specific)->pluck('subject_id'));
    }

    public function subjectIdsEnrolledBy(string $userId): array
    {
        return $this->ids(DB::table('classroom_students as e')
            ->join('classrooms as c', 'c.id', '=', 'e.classroom_id')
            ->join('classroom_subjects as s', 's.classroom_id', '=', 'e.classroom_id')
            ->where('e.user_id', $userId)
            ->whereNull('c.deactivated_at')
            ->distinct()
            ->pluck('s.subject_id'));
    }

    /**
     * @param  Collection<int, mixed>  $ids
     * @return list<string>
     */
    private function ids(Collection $ids): array
    {
        return array_values($ids
            ->map(fn (mixed $id): string => EloquentAttribute::string($id, 'classroom_subjects.subject_id'))
            ->unique()
            ->values()
            ->all());
    }
}
