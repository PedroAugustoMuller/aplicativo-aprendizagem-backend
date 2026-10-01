<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Persistence;

use App\Modules\Content\Domain\Entity\Subject;
use App\Modules\Content\Domain\ValueObject\SubjectId;
use App\Modules\Content\Domain\ValueObject\SubjectName;
use App\Shared\Infrastructure\Persistence\EloquentAttribute;
use DateTimeImmutable;

final class SubjectMapper
{
    public function toDomain(SubjectModel $model): Subject
    {
        $deactivatedAt = $model->getAttribute('deactivated_at');

        return Subject::restore(
            new SubjectId(EloquentAttribute::string($model->getKey(), 'subjects.id')),
            new SubjectName(EloquentAttribute::string($model->getAttribute('name'), 'subjects.name')),
            $deactivatedAt instanceof DateTimeImmutable ? $deactivatedAt : null,
        );
    }

    /** @return array<string, string|DateTimeImmutable|null> */
    public function toAttributes(Subject $subject): array
    {
        return [
            'name' => $subject->name()->value(),
            'deactivated_at' => $subject->deactivatedAt(),
        ];
    }
}
