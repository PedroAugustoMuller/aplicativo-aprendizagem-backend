<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Application\Service;

use App\Modules\Quiz\Domain\Entity\Attempt;
use App\Modules\Quiz\Domain\Exception\AttemptNotFoundException;
use App\Modules\Quiz\Domain\Exception\QuizAccessDeniedException;
use App\Shared\Domain\Auth\Actor;
use App\Shared\Domain\Contract\BankTopic;
use App\Shared\Domain\Contract\TeachingAssignments;

/** Behind role:student too: the route middleware is the early reject, this is the rule. */
final readonly class QuizAccess
{
    public function __construct(private TeachingAssignments $assignments) {}

    /** Starting a quiz: a student enrolled in the topic's subject. */
    public function assertCanPlay(Actor $actor, BankTopic $topic): void
    {
        if (! $actor->isStudent() || ! in_array($topic->subjectId, $this->assignments->subjectIdsEnrolledBy($actor->userId), true)) {
            throw new QuizAccessDeniedException;
        }
    }

    /**
     * Reading or answering: the student's own attempt, enrolled or not any more (it is
     * their history). Another student's attempt is reported as missing.
     */
    public function ownAttempt(Actor $actor, ?Attempt $attempt): Attempt
    {
        if (! $actor->isStudent()) {
            throw new QuizAccessDeniedException;
        }

        if ($attempt === null || ! $attempt->isOwnedBy($actor->userId)) {
            throw new AttemptNotFoundException;
        }

        return $attempt;
    }
}
