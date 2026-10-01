<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\Repository;

use App\Modules\Content\Domain\Entity\Subject;
use App\Modules\Content\Domain\ValueObject\SubjectId;
use App\Modules\Content\Domain\ValueObject\SubjectName;

interface SubjectRepository
{
    public function findById(SubjectId $id): ?Subject;

    public function nameTakenByAnother(SubjectName $name, SubjectId $except): bool;

    public function save(Subject $subject): void;
}
