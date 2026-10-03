<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\Entity;

use App\Modules\Content\Domain\ValueObject\SubjectId;
use App\Modules\Content\Domain\ValueObject\TopicId;
use App\Modules\Content\Domain\ValueObject\TopicName;
use DateTimeImmutable;
use InvalidArgumentException;

final class Topic
{
    public const DESCRIPTION_MAX_LENGTH = 500;

    /*
     * What changed since this instance was restored. Persistence writes only
     * these columns, so a rename and a concurrent deactivation of the same topic
     * never undo each other (the lost update the classroom fix 3daa360 removed).
     */
    private bool $isNew = true;

    private bool $detailsChanged = false;

    private bool $activationChanged = false;

    public function __construct(
        private readonly TopicId $id,
        private readonly SubjectId $subjectId,
        private TopicName $name,
        private string $description,
        private readonly int $position,
        private ?DateTimeImmutable $deactivatedAt = null,
    ) {
        if ($position < 0) {
            throw new InvalidArgumentException('Topic position cannot be negative.');
        }

        self::assertDescription($description);
    }

    public static function restore(
        TopicId $id,
        SubjectId $subjectId,
        TopicName $name,
        string $description,
        int $position,
        ?DateTimeImmutable $deactivatedAt,
    ): self {
        $topic = new self($id, $subjectId, $name, $description, $position, $deactivatedAt);
        $topic->isNew = false;

        return $topic;
    }

    public function id(): TopicId
    {
        return $this->id;
    }

    public function subjectId(): SubjectId
    {
        return $this->subjectId;
    }

    public function name(): TopicName
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
    }

    /** Ordering within the syllabus. Lower comes first. */
    public function position(): int
    {
        return $this->position;
    }

    public function deactivatedAt(): ?DateTimeImmutable
    {
        return $this->deactivatedAt;
    }

    public function isActive(): bool
    {
        return $this->deactivatedAt === null;
    }

    public function rename(TopicName $name): void
    {
        $this->name = $name;
        $this->detailsChanged = true;
    }

    public function changeDescription(string $description): void
    {
        $trimmed = trim($description);
        self::assertDescription($trimmed);

        $this->description = $trimmed;
        $this->detailsChanged = true;
    }

    /** Idempotent: deactivating an already-inactive topic keeps the first timestamp. */
    public function deactivate(DateTimeImmutable $at): void
    {
        if ($this->deactivatedAt === null) {
            $this->deactivatedAt = $at;
            $this->activationChanged = true;
        }
    }

    public function reactivate(): void
    {
        if ($this->deactivatedAt !== null) {
            $this->deactivatedAt = null;
            $this->activationChanged = true;
        }
    }

    /** Created in this request: everything about it must be written. */
    public function isNew(): bool
    {
        return $this->isNew;
    }

    /** Name or description changed since it was restored. */
    public function detailsChanged(): bool
    {
        return $this->detailsChanged;
    }

    /** Deactivated or reactivated since it was restored. */
    public function activationChanged(): bool
    {
        return $this->activationChanged;
    }

    private static function assertDescription(string $description): void
    {
        if (mb_strlen($description) > self::DESCRIPTION_MAX_LENGTH) {
            throw new InvalidArgumentException(
                sprintf('Topic description cannot exceed %d characters.', self::DESCRIPTION_MAX_LENGTH),
            );
        }
    }
}
