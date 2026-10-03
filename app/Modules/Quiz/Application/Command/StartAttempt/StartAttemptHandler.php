<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Application\Command\StartAttempt;

use App\Modules\Quiz\Application\DTO\AttemptView;
use App\Modules\Quiz\Application\Port\TransactionManager;
use App\Modules\Quiz\Application\Service\QuizAccess;
use App\Modules\Quiz\Domain\Entity\Attempt;
use App\Modules\Quiz\Domain\Exception\QuizTopicNotFoundException;
use App\Modules\Quiz\Domain\Exception\QuizTopicUnavailableException;
use App\Modules\Quiz\Domain\Repository\AttemptRepository;
use App\Modules\Quiz\Domain\Service\Shuffler;
use App\Modules\Quiz\Domain\ValueObject\AttemptId;
use App\Shared\Domain\Contract\BankTopic;
use App\Shared\Domain\Contract\QuestionBank;
use App\Shared\Domain\Exception\IdempotencyConflictException;
use DateTimeImmutable;

/**
 * Starting and downloading are the same request. Order: the same id resent → that
 * attempt; an open attempt on the topic → that one (no rerolling for easier
 * questions); otherwise a new one, if the topic can still be played.
 */
final readonly class StartAttemptHandler
{
    public function __construct(
        private QuestionBank $bank,
        private AttemptRepository $attempts,
        private QuizAccess $access,
        private TransactionManager $transactions,
        private Shuffler $shuffler,
    ) {}

    public function handle(StartAttemptCommand $command): StartedAttempt
    {
        $topic = $this->bank->topic($command->topicId) ?? throw new QuizTopicNotFoundException;
        $this->access->assertCanPlay($command->actor, $topic);

        return $this->transactions->run(fn (): StartedAttempt => $this->startOrResume($command, $topic));
    }

    private function startOrResume(StartAttemptCommand $command, BankTopic $topic): StartedAttempt
    {
        $studentId = $command->actor->userId;
        $id = new AttemptId($command->attemptId);
        // Two starts by one student (double tap, two tabs) run one after the other.
        $this->attempts->lockStudent($studentId);

        $existing = $this->attempts->findById($id);

        if ($existing !== null) {
            if (! $existing->isOwnedBy($studentId) || $existing->topicId() !== $topic->id) {
                throw new IdempotencyConflictException;
            }

            return new StartedAttempt(AttemptView::of($existing), false);
        }

        $open = $this->attempts->findOpen($studentId, $topic->id);

        if ($open !== null) {
            return new StartedAttempt(AttemptView::of($open), false);
        }

        if (! $topic->available) {
            throw new QuizTopicUnavailableException;
        }

        $attempt = Attempt::start($id, $studentId, $topic, $this->bank->activeQuestions($topic->id), $this->shuffler, new DateTimeImmutable);
        $this->attempts->add($attempt);

        return new StartedAttempt(AttemptView::of($attempt), true);
    }
}
