<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Entity;

use App\Modules\Identity\Domain\Exception\EnrolmentRequiresActiveStudentException;
use App\Modules\Identity\Domain\ValueObject\ClassroomId;
use App\Modules\Identity\Domain\ValueObject\ClassroomName;
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

    private bool $detailsChanged = false;

    /**
     * @param  array<string, UserId>  $teachers  keyed by id value
     * @param  array<string, UserId>  $students  keyed by id value
     */
    private function __construct(
        private readonly ClassroomId $id,
        private ClassroomName $name,
        private string $subjectId,
        private array $teachers,
        private array $students,
        private ?DateTimeImmutable $deactivatedAt,
        private readonly bool $isNew,
    ) {}

    public static function create(ClassroomId $id, ClassroomName $name, string $subjectId): self
    {
        return new self($id, $name, $subjectId, [], [], null, true);
    }

    /**
     * @param  list<UserId>  $teacherIds
     * @param  list<UserId>  $studentIds
     */
    public static function restore(ClassroomId $id, ClassroomName $name, string $subjectId, array $teacherIds, array $studentIds, ?DateTimeImmutable $deactivatedAt): self
    {
        return new self($id, $name, $subjectId, self::index($teacherIds), self::index($studentIds), $deactivatedAt, false);
    }

    public function id(): ClassroomId
    {
        return $this->id;
    }

    public function name(): ClassroomName
    {
        return $this->name;
    }

    public function subjectId(): string
    {
        return $this->subjectId;
    }

    /** @return list<UserId> */
    public function teacherIds(): array
    {
        return array_values($this->teachers);
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

    public function changeSubject(string $subjectId): void
    {
        $this->subjectId = $subjectId;
        $this->detailsChanged = true;
    }

    /** @param  list<User>  $teachers */
    public function assignTeachers(array $teachers): void
    {
        foreach ($teachers as $teacher) {
            if (! $teacher->role()->isStaff()) {
                throw new InvalidArgumentException('Only staff can teach a classroom.');
            }
        }

        $this->teachers = self::index(array_map(fn (User $t): UserId => $t->id(), $teachers));
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

    public function isTaughtBy(UserId $userId): bool
    {
        return isset($this->teachers[$userId->value()]);
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

    /** Name, subject or deactivation changed since it was loaded. */
    public function detailsChanged(): bool
    {
        return $this->detailsChanged;
    }

    /** The teacher list was replaced since it was loaded. */
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
