<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Quiz\Application;

use App\Modules\Quiz\Application\Command\AnswerQuestion\AnswerQuestionCommand;
use App\Modules\Quiz\Application\Command\AnswerQuestion\AnswerQuestionHandler;
use App\Modules\Quiz\Application\DTO\AnswerOutcome;
use App\Modules\Quiz\Application\Service\QuizAccess;
use App\Modules\Quiz\Domain\Entity\Attempt;
use App\Modules\Quiz\Domain\Exception\AttemptNotFoundException;
use App\Modules\Quiz\Domain\Exception\QuizAccessDeniedException;
use App\Modules\Quiz\Domain\ValueObject\AttemptId;
use App\Shared\Domain\Auth\Actor;
use App\Shared\Domain\Auth\Role;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Modules\Quiz\Support\BankFixtures;
use Tests\Unit\Modules\Quiz\Support\FixedAssignments;
use Tests\Unit\Modules\Quiz\Support\ImmediateTransactionManager;
use Tests\Unit\Modules\Quiz\Support\InMemoryAttemptRepository;
use Tests\Unit\Modules\Quiz\Support\ReversingShuffler;

final class AnswerQuestionHandlerTest extends TestCase
{
    private const STUDENT = '0192f0a0-0000-7000-8000-00000000b001';

    private InMemoryAttemptRepository $attempts;

    private Attempt $attempt;

    protected function setUp(): void
    {
        $this->attempts = new InMemoryAttemptRepository;
        $this->attempt = Attempt::start(AttemptId::random(), self::STUDENT, BankFixtures::topic(), BankFixtures::choices(2), new ReversingShuffler, new DateTimeImmutable('-1 minute'));
        $this->attempts->add($this->attempt);
    }

    private function answer(int $index, string $optionId, Actor $actor = new Actor(self::STUDENT, Role::Student), ?string $answerId = null): AnswerOutcome
    {
        return (new AnswerQuestionHandler($this->attempts, new QuizAccess(new FixedAssignments([])), new ImmediateTransactionManager))
            ->handle(new AnswerQuestionCommand(
                $actor,
                $this->attempt->id()->value(),
                $answerId ?? '0192f0a0-0000-7000-8000-0000000d000'.$index,
                $this->attempt->questions()[$index]->id()->value(),
                $optionId,
                new DateTimeImmutable,
            ));
    }

    public function test_an_answer_is_graded_saved_and_scored(): void
    {
        $outcome = $this->answer(0, BankFixtures::id(2, 1));

        self::assertTrue($outcome->result->correct);
        self::assertSame('Porque sim.', $outcome->result->explanation);
        self::assertSame(['total' => 2, 'answered' => 1, 'correct' => 1], (array) $outcome->score);
        self::assertFalse($outcome->completed);
        self::assertSame(1, $this->attempts->answersSaved);
    }

    public function test_the_last_answer_completes_the_quiz(): void
    {
        $this->answer(0, BankFixtures::id(2, 1));

        $outcome = $this->answer(1, BankFixtures::id(1, 3));

        self::assertTrue($outcome->completed);
        self::assertFalse($outcome->result->correct);
    }

    public function test_a_resent_answer_is_not_saved_twice(): void
    {
        $this->answer(0, BankFixtures::id(2, 1));

        $again = $this->answer(0, BankFixtures::id(2, 1));

        self::assertTrue($again->result->correct);
        self::assertSame(1, $this->attempts->answersSaved);
    }

    public function test_another_students_attempt_is_not_found(): void
    {
        $this->expectException(AttemptNotFoundException::class);

        $this->answer(0, BankFixtures::id(2, 1), new Actor('0192f0a0-0000-7000-8000-00000000b002', Role::Student));
    }

    public function test_staff_cannot_answer(): void
    {
        $this->expectException(QuizAccessDeniedException::class);

        $this->answer(0, BankFixtures::id(2, 1), new Actor(self::STUDENT, Role::Teacher));
    }
}
