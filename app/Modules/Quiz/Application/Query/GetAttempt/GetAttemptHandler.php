<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Application\Query\GetAttempt;

use App\Modules\Quiz\Application\DTO\AttemptView;
use App\Modules\Quiz\Application\Service\QuizAccess;
use App\Modules\Quiz\Domain\Repository\AttemptRepository;
use App\Modules\Quiz\Domain\ValueObject\AttemptId;

final readonly class GetAttemptHandler
{
    public function __construct(private AttemptRepository $attempts, private QuizAccess $access) {}

    public function handle(GetAttemptQuery $query): AttemptView
    {
        return AttemptView::of($this->access->ownAttempt($query->actor, $this->attempts->findById(new AttemptId($query->attemptId))));
    }
}
