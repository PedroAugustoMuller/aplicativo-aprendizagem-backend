<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Quiz\Support;

use App\Modules\Quiz\Domain\Entity\Attempt;
use App\Modules\Quiz\Domain\Repository\AttemptRepository;
use App\Modules\Quiz\Domain\ValueObject\AttemptId;

final class InMemoryAttemptRepository implements AttemptRepository
{
    /** @var array<string, Attempt> */
    public array $attempts = [];

    /** @var list<string> */
    public array $lockedStudents = [];

    public int $answersSaved = 0;

    public function findById(AttemptId $id): ?Attempt
    {
        return $this->attempts[$id->value()] ?? null;
    }

    public function findByIdForUpdate(AttemptId $id): ?Attempt
    {
        return $this->findById($id);
    }

    public function findOpen(string $studentId, string $topicId): ?Attempt
    {
        foreach ($this->attempts as $attempt) {
            if ($attempt->isOwnedBy($studentId) && $attempt->topicId() === $topicId && ! $attempt->isCompleted()) {
                return $attempt;
            }
        }

        return null;
    }

    public function lockStudent(string $studentId): void
    {
        $this->lockedStudents[] = $studentId;
    }

    public function add(Attempt $attempt): void
    {
        $this->attempts[$attempt->id()->value()] = $attempt;
    }

    public function saveAnswer(Attempt $attempt): void
    {
        if ($attempt->lastAnswered() !== null) {
            $this->answersSaved++;
        }
    }
}
