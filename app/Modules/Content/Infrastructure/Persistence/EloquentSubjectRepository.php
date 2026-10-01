<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Persistence;

use App\Modules\Content\Domain\Entity\Subject;
use App\Modules\Content\Domain\Repository\SubjectRepository;
use App\Modules\Content\Domain\ValueObject\SubjectId;
use App\Modules\Content\Domain\ValueObject\SubjectName;

final class EloquentSubjectRepository implements SubjectRepository
{
    public function __construct(private readonly SubjectMapper $mapper) {}

    public function findById(SubjectId $id): ?Subject
    {
        $model = SubjectModel::query()->find($id->value());

        return $model instanceof SubjectModel ? $this->mapper->toDomain($model) : null;
    }

    /**
     * Case-insensitive on purpose: the DB unique index on `name` stays case-sensitive
     * as a last line of defence, this check is the actual business rule.
     */
    public function nameTakenByAnother(SubjectName $name, SubjectId $except): bool
    {
        return SubjectModel::query()
            ->whereRaw('lower(name) = ?', [mb_strtolower($name->value())])
            ->whereKeyNot($except->value())
            ->exists();
    }

    public function save(Subject $subject): void
    {
        SubjectModel::query()->updateOrCreate(
            ['id' => $subject->id()->value()],
            $this->mapper->toAttributes($subject),
        );
    }
}
