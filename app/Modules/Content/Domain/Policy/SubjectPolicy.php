<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\Policy;

use App\Shared\Domain\Auth\Actor;
use App\Shared\Domain\Contract\TeachingAssignments;

/**
 * Who may see or author a subject's content. Depends on Shared's TeachingAssignments,
 * not Identity's — a module never imports another module's internals.
 */
final readonly class SubjectPolicy
{
    public function __construct(private TeachingAssignments $assignments) {}

    public function canView(Actor $actor, string $subjectId): bool
    {
        if ($actor->isStaff()) {
            return true;
        }

        return in_array($subjectId, $this->assignments->subjectIdsEnrolledBy($actor->userId), true);
    }

    /**
     * Topic and question authoring (RF02). Teachers share the bank of every subject they teach.
     *
     * @return list<string>|null null means every subject (admin)
     */
    public function authoredSubjectIds(Actor $actor): ?array
    {
        if ($actor->isAdmin()) {
            return null;
        }

        if ($actor->isStudent()) {
            return [];
        }

        return $this->assignments->subjectIdsTaughtBy($actor->userId);
    }

    public function canAuthor(Actor $actor, string $subjectId): bool
    {
        $ids = $this->authoredSubjectIds($actor);

        return $ids === null || in_array($subjectId, $ids, true);
    }
}
