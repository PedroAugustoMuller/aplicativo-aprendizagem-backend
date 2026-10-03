<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Application\Command\AnswerQuestion;

use App\Modules\Quiz\Application\DTO\AnswerOutcome;
use App\Modules\Quiz\Application\Port\TransactionManager;
use App\Modules\Quiz\Application\Service\QuizAccess;
use App\Modules\Quiz\Domain\Repository\AttemptRepository;
use App\Modules\Quiz\Domain\ValueObject\AnswerId;
use App\Modules\Quiz\Domain\ValueObject\AttemptId;
use App\Modules\Quiz\Domain\ValueObject\AttemptQuestionId;
use DateTimeImmutable;

final readonly class AnswerQuestionHandler
{
    public function __construct(
        private AttemptRepository $attempts,
        private QuizAccess $access,
        private TransactionManager $transactions,
    ) {}

    public function handle(AnswerQuestionCommand $command): AnswerOutcome
    {
        return $this->transactions->run(function () use ($command): AnswerOutcome {
            // The row lock queues answers to one attempt; grading and writing happen under it.
            $attempt = $this->access->ownAttempt($command->actor, $this->attempts->findByIdForUpdate(new AttemptId($command->attemptId)));

            $result = $attempt->answer(
                new AttemptQuestionId($command->questionId),
                $command->optionId,
                new AnswerId($command->answerId),
                $command->answeredAt,
                new DateTimeImmutable,
            );
            $this->attempts->saveAnswer($attempt);

            return new AnswerOutcome($result, $attempt->score(), $attempt->isCompleted());
        });
    }
}
