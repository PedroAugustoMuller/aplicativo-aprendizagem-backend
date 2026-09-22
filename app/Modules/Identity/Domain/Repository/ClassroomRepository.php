<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Repository;

use App\Modules\Identity\Domain\Entity\Classroom;
use App\Modules\Identity\Domain\ValueObject\ClassroomId;
use App\Modules\Identity\Domain\ValueObject\ClassroomName;
use App\Modules\Identity\Domain\ValueObject\UserId;

interface ClassroomRepository
{
    public function findById(ClassroomId $id): ?Classroom;

    public function nameTakenByAnother(ClassroomName $name, ClassroomId $except): bool;

    /** @return list<Classroom> */
    public function findByStudent(UserId $studentId): array;

    public function save(Classroom $classroom): void;
}
