<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Domain\Repository;

use App\Modules\Quiz\Domain\Entity\Attempt;
use App\Modules\Quiz\Domain\ValueObject\AttemptId;

interface AttemptRepository
{
    public function findById(AttemptId $id): ?Attempt;

    /** Locks the attempt until the surrounding transaction ends: answers to it queue up. */
    public function findByIdForUpdate(AttemptId $id): ?Attempt;

    public function findOpen(string $studentId, string $topicId): ?Attempt;

    /** Serialises one student's starts until the surrounding transaction ends. */
    public function lockStudent(string $studentId): void;

    public function add(Attempt $attempt): void;

    /** Writes the question the last answer() newly answered, and completion. */
    public function saveAnswer(Attempt $attempt): void;
}
