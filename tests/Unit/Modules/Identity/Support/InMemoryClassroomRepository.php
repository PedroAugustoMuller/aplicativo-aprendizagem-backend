<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity\Support;

use App\Modules\Identity\Domain\Entity\Classroom;
use App\Modules\Identity\Domain\Repository\ClassroomRepository;
use App\Modules\Identity\Domain\ValueObject\ClassroomId;
use App\Modules\Identity\Domain\ValueObject\ClassroomName;
use App\Modules\Identity\Domain\ValueObject\UserId;

final class InMemoryClassroomRepository implements ClassroomRepository
{
    /** @var array<string, Classroom> */
    public array $byId = [];

    public function findById(ClassroomId $id): ?Classroom
    {
        return $this->byId[$id->value()] ?? null;
    }

    public function nameTakenByAnother(ClassroomName $name, ClassroomId $except): bool
    {
        foreach ($this->byId as $classroom) {
            if (! $classroom->id()->equals($except) && mb_strtolower($classroom->name()->value()) === mb_strtolower($name->value())) {
                return true;
            }
        }

        return false;
    }

    public function findByStudent(UserId $studentId): array
    {
        return array_values(array_filter(
            $this->byId,
            fn (Classroom $classroom): bool => $classroom->hasStudent($studentId),
        ));
    }

    public function save(Classroom $classroom): void
    {
        $this->byId[$classroom->id()->value()] = $classroom;
    }
}
