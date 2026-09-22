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
    ) {}

    public static function create(ClassroomId $id, ClassroomName $name, string $subjectId): self
    {
        return new self($id, $name, $subjectId, [], [], null);
    }

    /**
     * @param  list<UserId>  $teacherIds
     * @param  list<UserId>  $studentIds
     */
    public static function restore(ClassroomId $id, ClassroomName $name, string $subjectId, array $teacherIds, array $studentIds, ?DateTimeImmutable $deactivatedAt): self
    {
        return new self($id, $name, $subjectId, self::index($teacherIds), self::index($studentIds), $deactivatedAt);
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
    }

    public function changeSubject(string $subjectId): void
    {
        $this->subjectId = $subjectId;
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
    }

    public function enrol(User $student): void
    {
        if ($student->role() !== Role::Student || ! $student->isActive()) {
            throw new EnrolmentRequiresActiveStudentException;
        }

        $this->students[$student->id()->value()] = $student->id();
    }

    public function unenrol(UserId $studentId): void
    {
        unset($this->students[$studentId->value()]);
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
        $this->deactivatedAt ??= $at;
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
