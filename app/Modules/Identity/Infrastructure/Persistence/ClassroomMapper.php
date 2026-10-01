<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Persistence;

use App\Modules\Identity\Domain\Entity\Classroom;
use App\Modules\Identity\Domain\ValueObject\ClassroomId;
use App\Modules\Identity\Domain\ValueObject\ClassroomName;
use App\Modules\Identity\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\EloquentAttribute;
use DateTimeImmutable;

final class ClassroomMapper
{
    /**
     * @param  list<string>  $teacherIds
     * @param  list<string>  $studentIds
     */
    public function toDomain(ClassroomModel $model, array $teacherIds, array $studentIds): Classroom
    {
        $deactivatedAt = $model->getAttribute('deactivated_at');

        return Classroom::restore(
            new ClassroomId(EloquentAttribute::string($model->getKey(), 'classrooms.id')),
            new ClassroomName(EloquentAttribute::string($model->getAttribute('name'), 'classrooms.name')),
            EloquentAttribute::string($model->getAttribute('subject_id'), 'classrooms.subject_id'),
            array_map(static fn (string $id): UserId => new UserId($id), $teacherIds),
            array_map(static fn (string $id): UserId => new UserId($id), $studentIds),
            $deactivatedAt instanceof DateTimeImmutable ? $deactivatedAt : null,
        );
    }

    /** @return array<string, string|DateTimeImmutable|null> */
    public function toAttributes(Classroom $classroom): array
    {
        return [
            'name' => $classroom->name()->value(),
            'subject_id' => $classroom->subjectId(),
            'deactivated_at' => $classroom->deactivatedAt(),
        ];
    }
}
