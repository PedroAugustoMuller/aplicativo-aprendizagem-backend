<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\Entity;

use App\Modules\Content\Domain\ValueObject\SubjectId;
use App\Modules\Content\Domain\ValueObject\SubjectName;
use DateTimeImmutable;

final class Subject
{
    private function __construct(
        private readonly SubjectId $id,
        private SubjectName $name,
        private ?DateTimeImmutable $deactivatedAt,
    ) {}

    public static function create(SubjectId $id, SubjectName $name): self
    {
        return new self($id, $name, null);
    }

    public static function restore(SubjectId $id, SubjectName $name, ?DateTimeImmutable $deactivatedAt): self
    {
        return new self($id, $name, $deactivatedAt);
    }

    public function id(): SubjectId
    {
        return $this->id;
    }

    public function name(): SubjectName
    {
        return $this->name;
    }

    public function deactivatedAt(): ?DateTimeImmutable
    {
        return $this->deactivatedAt;
    }

    public function isActive(): bool
    {
        return $this->deactivatedAt === null;
    }

    public function rename(SubjectName $name): void
    {
        $this->name = $name;
    }

    /** Idempotent: deactivating an already-inactive subject keeps the first timestamp. */
    public function deactivate(DateTimeImmutable $at): void
    {
        $this->deactivatedAt ??= $at;
    }
}
