<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Persistence;

use App\Modules\Identity\Domain\Entity\Classroom;
use App\Modules\Identity\Domain\Repository\ClassroomRepository;
use App\Modules\Identity\Domain\ValueObject\ClassroomId;
use App\Modules\Identity\Domain\ValueObject\ClassroomName;
use App\Modules\Identity\Domain\ValueObject\TeachingAssignment;
use App\Modules\Identity\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\EloquentAttribute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EloquentClassroomRepository implements ClassroomRepository
{
    public function __construct(private readonly ClassroomMapper $mapper) {}

    public function findById(ClassroomId $id): ?Classroom
    {
        $model = ClassroomModel::query()->find($id->value());

        if (! $model instanceof ClassroomModel) {
            return null;
        }

        $ids = [$id->value()];

        return $this->mapper->toDomain(
            $model,
            $this->subjectsByClassroom($ids)[$id->value()] ?? [],
            $this->assignmentsByClassroom($ids)[$id->value()] ?? [],
            $this->pivotUserIdsGroupedByClassroom('classroom_students', $ids)[$id->value()] ?? [],
        );
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
     * Loads every matching classroom plus subjects, assignments and students in four
     * queries total, regardless of how many classrooms the student is in — findById()
     * in a loop would cost a query per classroom (an N+1 once a caller such as
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

        $ids = array_values($models
            ->map(fn (ClassroomModel $model): string => EloquentAttribute::string($model->getKey(), 'classrooms.id'))
            ->all());

        $subjects = $this->subjectsByClassroom($ids);
        $assignments = $this->assignmentsByClassroom($ids);
        $students = $this->pivotUserIdsGroupedByClassroom('classroom_students', $ids);

        return array_values($models
            ->map(function (ClassroomModel $model) use ($subjects, $assignments, $students): Classroom {
                $id = EloquentAttribute::string($model->getKey(), 'classrooms.id');

                return $this->mapper->toDomain($model, $subjects[$id] ?? [], $assignments[$id] ?? [], $students[$id] ?? []);
            })
            ->all());
    }

    public function save(Classroom $classroom): void
    {
        // Only what this request changed is written: another request may have
        // changed the same classroom since this one loaded it, and writing the
        // whole snapshot back would silently undo that (lost update).
        DB::transaction(function () use ($classroom): void {
            $classroomId = $classroom->id()->value();

            if ($classroom->isNew() || $classroom->detailsChanged()) {
                ClassroomModel::query()->updateOrCreate(['id' => $classroomId], $this->mapper->toAttributes($classroom));
            }

            if ($classroom->isNew() || $classroom->subjectsChanged()) {
                $this->replaceSubjects($classroomId, $classroom->subjectIds());
            }

            if ($classroom->isNew() || $classroom->teachersChanged()) {
                $this->replaceAssignments($classroomId, $classroom->assignments());
            }

            $removed = self::values($classroom->unenrolledStudentIds());
            if ($removed !== []) {
                DB::table('classroom_students')->where('classroom_id', $classroomId)->whereIn('user_id', $removed)->delete();
            }

            $added = self::values($classroom->enrolledStudentIds());
            if ($added !== []) {
                DB::table('classroom_students')->insertOrIgnore(array_map(
                    static fn (string $userId): array => ['classroom_id' => $classroomId, 'user_id' => $userId],
                    $added,
                ));
            }
        });
    }

    /**
     * @param  list<UserId>  $ids
     * @return list<string>
     */
    private static function values(array $ids): array
    {
        return array_map(static fn (UserId $id): string => $id->value(), $ids);
    }

    /** @param  list<string>  $subjectIds */
    private function replaceSubjects(string $classroomId, array $subjectIds): void
    {
        DB::table('classroom_subjects')
            ->where('classroom_id', $classroomId)
            ->when($subjectIds !== [], fn ($query) => $query->whereNotIn('subject_id', $subjectIds))
            ->delete();

        if ($subjectIds !== []) {
            DB::table('classroom_subjects')->insertOrIgnore(array_map(
                static fn (string $subjectId): array => ['classroom_id' => $classroomId, 'subject_id' => $subjectId],
                $subjectIds,
            ));
        }
    }

    /**
     * The assignment list is replaced as a whole, like the teacher list was.
     *
     * @param  list<TeachingAssignment>  $assignments
     */
    private function replaceAssignments(string $classroomId, array $assignments): void
    {
        DB::table('classroom_teachers')->where('classroom_id', $classroomId)->delete();

        if ($assignments === []) {
            return;
        }

        DB::table('classroom_teachers')->insert(array_map(
            static fn (TeachingAssignment $a): array => [
                'id' => (string) Str::uuid7(), 'classroom_id' => $classroomId,
                'user_id' => $a->teacherId->value(), 'subject_id' => $a->subjectId,
            ],
            $assignments,
        ));
    }

    /**
     * @param  list<string>  $classroomIds
     * @return array<string, list<string>>
     */
    private function subjectsByClassroom(array $classroomIds): array
    {
        $grouped = [];
        foreach (DB::table('classroom_subjects')->whereIn('classroom_id', $classroomIds)->orderBy('subject_id')->get(['classroom_id', 'subject_id']) as $row) {
            $grouped[EloquentAttribute::string($row->classroom_id, 'classroom_subjects.classroom_id')][] = EloquentAttribute::string($row->subject_id, 'classroom_subjects.subject_id');
        }

        return $grouped;
    }

    /**
     * @param  list<string>  $classroomIds
     * @return array<string, list<array{user_id: string, subject_id: ?string}>>
     */
    private function assignmentsByClassroom(array $classroomIds): array
    {
        $grouped = [];
        foreach (DB::table('classroom_teachers')->whereIn('classroom_id', $classroomIds)->get(['classroom_id', 'user_id', 'subject_id']) as $row) {
            $grouped[EloquentAttribute::string($row->classroom_id, 'classroom_teachers.classroom_id')][] = [
                'user_id' => EloquentAttribute::string($row->user_id, 'classroom_teachers.user_id'),
                'subject_id' => $row->subject_id === null ? null : EloquentAttribute::string($row->subject_id, 'classroom_teachers.subject_id'),
            ];
        }

        return $grouped;
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
