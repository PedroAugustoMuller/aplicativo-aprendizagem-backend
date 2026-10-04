<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Application\Query\GetStudentAttempt;

use App\Modules\Quiz\Application\DTO\AttemptView;
use App\Modules\Quiz\Application\Service\ProgressAccess;
use App\Modules\Quiz\Domain\Exception\AttemptNotFoundException;
use App\Modules\Quiz\Domain\Repository\AttemptRepository;
use App\Modules\Quiz\Domain\ValueObject\AttemptId;

final readonly class GetStudentAttemptHandler
{
    public function __construct(private AttemptRepository $attempts, private ProgressAccess $access) {}

    /**
     * A classroom student's attempt, for staff. Completed attempts only: the unanswered
     * questions of an open one would show the answer key, and other subjects are not
     * this classroom's business.
     */
    public function handle(GetStudentAttemptQuery $query): AttemptView
    {
        $scope = $this->access->scopeFor($query->actor, $query->classroomId, $query->studentId);
        $attempt = $this->attempts->findById(new AttemptId($query->attemptId));

        if ($attempt === null || ! $attempt->isOwnedBy($scope->studentId) || $attempt->subjectId() !== $scope->subjectId || ! $attempt->isCompleted()) {
            throw new AttemptNotFoundException;
        }

        return AttemptView::of($attempt);
    }
}
