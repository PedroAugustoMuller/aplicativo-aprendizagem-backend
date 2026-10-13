<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Entity;

use App\Modules\Identity\Domain\Exception\ClassroomSubjectNotAssignedException;
use App\Modules\Identity\Domain\Exception\ClassroomTeacherAssignmentRedundantException;
use App\Modules\Identity\Domain\Exception\EnrolmentRequiresActiveStudentException;
use App\Modules\Identity\Domain\ValueObject\ClassroomId;
use App\Modules\Identity\Domain\ValueObject\ClassroomName;
use App\Modules\Identity\Domain\ValueObject\TeachingAssignment;
use App\Modules\Identity\Domain\ValueObject\UserId;
use App\Shared\Domain\Auth\Role;
use DateTimeImmutable;
use InvalidArgumentException;

final class Classroom
{
    /*
     * What changed since this instance was created or restored. Persistence
     * writes these deltas, never the whole snapshot: two requests that loaded
     * the same classroom (two teachers adding students at once, a teacher
     * enrolling while the admin renames) must not erase each other's changes.
     */

    /** @var array<string, UserId> */
    private array $enrolled = [];

    /** @var array<string, UserId> */
    private array $unenrolled = [];

    private bool $teachersChanged = false;

    private bool $subjectsChanged = false;

    private bool $detailsChanged = false;

    /**
     * @param  array<string, string>  $subjectIds  keyed by itself
     * @param  array<string, TeachingAssignment>  $assignments  keyed by TeachingAssignment::key()
     * @param  array<string, UserId>  $students  keyed by id value
     */
    private function __construct(
        private readonly ClassroomId $id,
        private ClassroomName $name,
        private array $subjectIds,
        private array $assignments,
        private array $students,
        private ?DateTimeImmutable $deactivatedAt,
        private readonly bool $isNew,
    ) {}

    /** @param  list<string>  $subjectIds */
    public static function create(ClassroomId $id, ClassroomName $name, array $subjectIds): self
    {
        return new self($id, $name, self::subjectSet($subjectIds), [], [], null, true);
    }

    /**
     * @param  list<string>  $subjectIds
     * @param  list<TeachingAssignment>  $assignments
     * @param  list<UserId>  $studentIds
     */
    public static function restore(ClassroomId $id, ClassroomName $name, array $subjectIds, array $assignments, array $studentIds, ?DateTimeImmutable $deactivatedAt): self
    {
        return new self($id, $name, self::subjectSet($subjectIds), self::assignmentSet($assignments), self::index($studentIds), $deactivatedAt, false);
    }

    public function id(): ClassroomId
    {
        return $this->id;
    }

    public function name(): ClassroomName
    {
        return $this->name;
    }

    /** @return list<string> */
    public function subjectIds(): array
    {
        return array_values($this->subjectIds);
    }

    public function hasSubject(string $subjectId): bool
    {
        return isset($this->subjectIds[$subjectId]);
    }

    /** @return list<TeachingAssignment> */
    public function assignments(): array
    {
        return array_values($this->assignments);
    }

    /** @return list<UserId> */
    public function studentIds(): array
    {
        return array_values($this->students);
    }

    public function deactivatedAt(): ?DateTimeImmutable
    {
        return $this->deactivatedAt;
    }

    public function isActive(): bool
    {
        return $this->deactivatedAt === null;
    }

    public function rename(ClassroomName $name): void
    {
        $this->name = $name;
        $this->detailsChanged = true;
    }

    /**
     * Assignments to a subject that leaves the classroom go with it.
     *
     * @param  list<string>  $subjectIds
     */
    public function replaceSubjects(array $subjectIds): void
    {
        $this->subjectIds = self::subjectSet($subjectIds);
        $this->subjectsChanged = true;

        $kept = array_filter(
            $this->assignments,
            fn (TeachingAssignment $a): bool => $a->subjectId === null || $this->hasSubject($a->subjectId),
        );

        if (count($kept) !== count($this->assignments)) {
            $this->assignments = $kept;
            $this->teachersChanged = true;
        }
    }

    /** @param  list<array{0: User, 1: ?string}>  $assignments  teacher and subject (null: whole classroom) */
    public function replaceAssignments(array $assignments): void
    {
        $next = [];
        $whole = [];

        foreach ($assignments as [$teacher, $subjectId]) {
            if (! $teacher->role()->isStaff()) {
                throw new InvalidArgumentException('Only staff can teach a classroom.');
            }

            if ($subjectId !== null && ! $this->hasSubject($subjectId)) {
                throw new ClassroomSubjectNotAssignedException;
            }

            $assignment = new TeachingAssignment($teacher->id(), $subjectId);
            $next[$assignment->key()] = $assignment;

            if ($subjectId === null) {
                $whole[$teacher->id()->value()] = true;
            }
        }

        foreach ($next as $assignment) {
            if (! $assignment->isWholeClassroom() && isset($whole[$assignment->teacherId->value()])) {
                throw new ClassroomTeacherAssignmentRedundantException;
            }
        }

        $this->assignments = $next;
        $this->teachersChanged = true;
    }

    public function enrol(User $student): void
    {
        if ($student->role() !== Role::Student || ! $student->isActive()) {
            throw new EnrolmentRequiresActiveStudentException;
        }

        $key = $student->id()->value();
        $this->students[$key] = $student->id();
        $this->enrolled[$key] = $student->id();
        unset($this->unenrolled[$key]);
    }

    public function unenrol(UserId $studentId): void
    {
        $key = $studentId->value();
        unset($this->students[$key], $this->enrolled[$key]);
        $this->unenrolled[$key] = $studentId;
    }

    /** Any assignment: may open the classroom and manage its roster. */
    public function isTaughtBy(UserId $userId): bool
    {
        foreach ($this->assignments as $assignment) {
            if ($assignment->teacherId->equals($userId)) {
                return true;
            }
        }

        return false;
    }

    public function teaches(UserId $userId, string $subjectId): bool
    {
        if (! $this->hasSubject($subjectId)) {
            return false;
        }

        foreach ($this->assignments as $assignment) {
            if ($assignment->teacherId->equals($userId) && ($assignment->isWholeClassroom() || $assignment->subjectId === $subjectId)) {
                return true;
            }
        }

        return false;
    }

    public function hasStudent(UserId $userId): bool
    {
        return isset($this->students[$userId->value()]);
    }

    /** Idempotent: deactivating an already-inactive classroom keeps the first timestamp. */
    public function deactivate(DateTimeImmutable $at): void
    {
        if ($this->deactivatedAt === null) {
            $this->deactivatedAt = $at;
            $this->detailsChanged = true;
        }
    }

    /** Created in this request: everything about it must be written. */
    public function isNew(): bool
    {
        return $this->isNew;
    }

    /** Name or deactivation changed since it was loaded. */
    public function detailsChanged(): bool
    {
        return $this->detailsChanged;
    }

    /** The subject list was replaced since it was loaded. */
    public function subjectsChanged(): bool
    {
        return $this->subjectsChanged;
    }

    /** The assignments were replaced (or lost to a removed subject) since it was loaded. */
    public function teachersChanged(): bool
    {
        return $this->teachersChanged;
    }

    /** @return list<UserId> students enrolled since it was loaded */
    public function enrolledStudentIds(): array
    {
        return array_values($this->enrolled);
    }

    /** @return list<UserId> students unenrolled since it was loaded */
    public function unenrolledStudentIds(): array
    {
        return array_values($this->unenrolled);
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, string>
     */
    private static function subjectSet(array $ids): array
    {
        $set = [];
        foreach ($ids as $id) {
            $set[$id] = $id;
        }

        return $set;
    }

    /**
     * @param  list<TeachingAssignment>  $assignments
     * @return array<string, TeachingAssignment>
     */
    private static function assignmentSet(array $assignments): array
    {
        $set = [];
        foreach ($assignments as $assignment) {
            $set[$assignment->key()] = $assignment;
        }

        return $set;
    }

    /**
     * @param  list<UserId>  $ids
     * @return array<string, UserId>
     */
    private static function index(array $ids): array
    {
        $indexed = [];
        foreach ($ids as $id) {
            $indexed[$id->value()] = $id;
        }

        return $indexed;
    }
}
