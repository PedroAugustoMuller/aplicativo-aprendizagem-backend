<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Quiz\Support;

use App\Shared\Domain\Contract\TeachingAssignments;

final class FixedAssignments implements TeachingAssignments
{
    /** @param list<string> $enrolled */
    public function __construct(private readonly array $enrolled) {}

    public function subjectIdsTaughtBy(string $userId): array
    {
        return [];
    }

    public function subjectIdsEnrolledBy(string $userId): array
    {
        return $this->enrolled;
    }
}
