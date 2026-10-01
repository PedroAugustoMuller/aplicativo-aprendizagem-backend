<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\DTO;

use App\Modules\Identity\Domain\Entity\Classroom;
use App\Modules\Identity\Domain\ValueObject\UserId;

final readonly class ClassroomView
{
    /** @param  list<string>  $teacherIds */
    public function __construct(
        public string $id,
        public string $name,
        public string $subjectId,
        public array $teacherIds,
        public int $studentCount,
        public bool $active,
    ) {}

    public static function of(Classroom $classroom): self
    {
        return new self(
            $classroom->id()->value(),
            $classroom->name()->value(),
            $classroom->subjectId(),
            array_map(fn (UserId $id): string => $id->value(), $classroom->teacherIds()),
            count($classroom->studentIds()),
            $classroom->isActive(),
        );
    }
}
