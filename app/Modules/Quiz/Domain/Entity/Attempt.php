<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Domain\Entity;

use App\Modules\Quiz\Domain\Exception\InvalidAnswerOptionException;
use App\Modules\Quiz\Domain\Exception\QuestionAlreadyAnsweredException;
use App\Modules\Quiz\Domain\Exception\TopicHasNoQuestionsException;
use App\Modules\Quiz\Domain\Service\Shuffler;
use App\Modules\Quiz\Domain\ValueObject\AnswerId;
use App\Modules\Quiz\Domain\ValueObject\AnswerResult;
use App\Modules\Quiz\Domain\ValueObject\AttemptId;
use App\Modules\Quiz\Domain\ValueObject\AttemptQuestionId;
use App\Modules\Quiz\Domain\ValueObject\Score;
use App\Shared\Domain\Contract\BankQuestion;
use App\Shared\Domain\Contract\BankTopic;
use App\Shared\Domain\Exception\IdempotencyConflictException;
use DateTimeImmutable;

/** One student's quiz on one topic: the questions drawn when it started, and their answers. */
final class Attempt
{
    public const MAX_QUESTIONS = 10;

    /** The question the last answer() call newly answered; what the repository must write. */
    private ?AttemptQuestion $lastAnswered = null;

    /** @param list<AttemptQuestion> $questions in position order */
    private function __construct(
        private readonly AttemptId $id,
        private readonly string $studentId,
        private readonly string $topicId,
        private readonly string $subjectId,
        private readonly DateTimeImmutable $startedAt,
        private ?DateTimeImmutable $completedAt,
        private readonly array $questions,
    ) {}

    /** @param list<BankQuestion> $bank the topic's active questions */
    public static function start(AttemptId $id, string $studentId, BankTopic $topic, array $bank, Shuffler $shuffler, DateTimeImmutable $now): self
    {
        if ($bank === []) {
            throw new TopicHasNoQuestionsException;
        }

        $questions = [];

        foreach (array_slice($shuffler->shuffle($bank), 0, self::MAX_QUESTIONS) as $position => $question) {
            $questions[] = AttemptQuestion::snapshot(AttemptQuestionId::random(), $position, $question, $shuffler);
        }

        return new self($id, $studentId, $topic->id, $topic->subjectId, $now, null, $questions);
    }

    /** @param list<AttemptQuestion> $questions in position order */
    public static function restore(
        AttemptId $id,
        string $studentId,
        string $topicId,
        string $subjectId,
        DateTimeImmutable $startedAt,
        ?DateTimeImmutable $completedAt,
        array $questions,
    ): self {
        return new self($id, $studentId, $topicId, $subjectId, $startedAt, $completedAt, $questions);
    }

    /**
     * Grades one answer against the snapshot. Resending the same answer id returns the
     * stored result unchanged (a phone retrying after a timeout); any other second
     * answer to the question is refused.
     */
    public function answer(AttemptQuestionId $questionId, string $optionId, AnswerId $answerId, DateTimeImmutable $answeredAt, DateTimeImmutable $receivedAt): AnswerResult
    {
        $this->lastAnswered = null;
        $question = $this->question($questionId) ?? throw new InvalidAnswerOptionException;
        $stored = $question->result();

        if ($stored !== null) {
            if ($question->answerId()?->equals($answerId) === true) {
                return $stored;
            }

            throw new QuestionAlreadyAnsweredException;
        }

        foreach ($this->questions as $other) {
            if ($other->answerId()?->equals($answerId) === true) {
                throw new IdempotencyConflictException;
            }
        }

        $result = $question->answer($answerId, $optionId, $this->clamp($answeredAt, $receivedAt));
        $this->lastAnswered = $question;

        if ($this->score()->answered === count($this->questions)) {
            $this->completedAt = $receivedAt;
        }

        return $result;
    }

    /** A phone's clock can be anything: keep the time inside the attempt's real window. */
    private function clamp(DateTimeImmutable $answeredAt, DateTimeImmutable $receivedAt): DateTimeImmutable
    {
        if ($answeredAt < $this->startedAt) {
            return $this->startedAt;
        }

        return $answeredAt > $receivedAt ? $receivedAt : $answeredAt;
    }

    private function question(AttemptQuestionId $id): ?AttemptQuestion
    {
        foreach ($this->questions as $question) {
            if ($question->id()->equals($id)) {
                return $question;
            }
        }

        return null;
    }

    public function score(): Score
    {
        $answered = 0;
        $correct = 0;

        foreach ($this->questions as $question) {
            $verdict = $question->isCorrect();

            if ($verdict !== null) {
                $answered++;
                $correct += $verdict ? 1 : 0;
            }
        }

        return new Score(count($this->questions), $answered, $correct);
    }

    public function isOwnedBy(string $studentId): bool
    {
        return $this->studentId === $studentId;
    }

    public function isCompleted(): bool
    {
        return $this->completedAt !== null;
    }

    public function lastAnswered(): ?AttemptQuestion
    {
        return $this->lastAnswered;
    }

    public function id(): AttemptId
    {
        return $this->id;
    }

    public function studentId(): string
    {
        return $this->studentId;
    }

    public function topicId(): string
    {
        return $this->topicId;
    }

    public function subjectId(): string
    {
        return $this->subjectId;
    }

    public function startedAt(): DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function completedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    /** @return list<AttemptQuestion> */
    public function questions(): array
    {
        return $this->questions;
    }
}
