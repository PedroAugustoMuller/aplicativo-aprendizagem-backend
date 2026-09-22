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

    /** Topic and question authoring (RF02). Teachers share the bank of every subject they teach. */
    public function canAuthor(Actor $actor, string $subjectId): bool
    {
        if ($actor->isAdmin()) {
            return true;
        }

        if ($actor->isStudent()) {
            return false;
        }

        return in_array($subjectId, $this->assignments->subjectIdsTaughtBy($actor->userId), true);
    }
}
