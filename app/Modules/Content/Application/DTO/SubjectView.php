<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\DTO;

use App\Modules\Content\Domain\Entity\Subject;

final readonly class SubjectView
{
    public function __construct(
        public string $id,
        public string $name,
        public bool $active,
    ) {}

    public static function of(Subject $subject): self
    {
        return new self($subject->id()->value(), $subject->name()->value(), $subject->isActive());
    }
}
