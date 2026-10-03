<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Quiz\Application;

use App\Modules\Quiz\Application\Command\StartAttempt\StartAttemptCommand;
use App\Modules\Quiz\Application\Command\StartAttempt\StartAttemptHandler;
use App\Modules\Quiz\Application\Command\StartAttempt\StartedAttempt;
use App\Modules\Quiz\Application\Service\QuizAccess;
use App\Modules\Quiz\Domain\Exception\QuizAccessDeniedException;
use App\Modules\Quiz\Domain\Exception\QuizTopicNotFoundException;
use App\Modules\Quiz\Domain\Exception\QuizTopicUnavailableException;
use App\Modules\Quiz\Domain\Exception\TopicHasNoQuestionsException;
use App\Modules\Quiz\Domain\ValueObject\AnswerId;
use App\Modules\Quiz\Domain\ValueObject\AttemptQuestionId;
use App\Shared\Domain\Auth\Actor;
use App\Shared\Domain\Auth\Role;
use App\Shared\Domain\Exception\IdempotencyConflictException;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Modules\Quiz\Support\BankFixtures;
use Tests\Unit\Modules\Quiz\Support\FakeQuestionBank;
use Tests\Unit\Modules\Quiz\Support\FixedAssignments;
use Tests\Unit\Modules\Quiz\Support\ImmediateTransactionManager;
use Tests\Unit\Modules\Quiz\Support\InMemoryAttemptRepository;
use Tests\Unit\Modules\Quiz\Support\ReversingShuffler;

final class StartAttemptHandlerTest extends TestCase
{
    private const STUDENT = '0192f0a0-0000-7000-8000-00000000b001';

    private const FIRST = '0192f0a0-0000-7000-8000-00000000c001';

    private const SECOND = '0192f0a0-0000-7000-8000-00000000c002';

    private InMemoryAttemptRepository $attempts;

    private FakeQuestionBank $bank;

    private ImmediateTransactionManager $transactions;

    protected function setUp(): void
    {
        $this->attempts = new InMemoryAttemptRepository;
        $this->bank = new FakeQuestionBank(BankFixtures::topic(), BankFixtures::choices(12));
        $this->transactions = new ImmediateTransactionManager;
    }

    /** @param list<string> $enrolled */
    private function handler(array $enrolled = [BankFixtures::SUBJECT]): StartAttemptHandler
    {
        return new StartAttemptHandler($this->bank, $this->attempts, new QuizAccess(new FixedAssignments($enrolled)), $this->transactions, new ReversingShuffler);
    }

    private function start(string $attemptId = self::FIRST, Role $role = Role::Student, string $topicId = BankFixtures::TOPIC): StartedAttempt
    {
        return $this->handler()->handle(new StartAttemptCommand(new Actor(self::STUDENT, $role), $topicId, $attemptId));
    }

    public function test_an_enrolled_student_starts_a_quiz_of_ten_questions(): void
    {
        $started = $this->start();

        self::assertTrue($started->created);
        self::assertSame(self::FIRST, $started->view->id);
        self::assertCount(10, $started->view->questions);
        self::assertSame(10, $started->view->score->total);
        self::assertSame([self::STUDENT], $this->attempts->lockedStudents);
        self::assertSame(1, $this->transactions->runs);
    }

    public function test_staff_cannot_play(): void
    {
        $this->expectException(QuizAccessDeniedException::class);

        $this->start(role: Role::Teacher);
    }

    public function test_a_student_not_enrolled_in_the_subject_cannot_play(): void
    {
        $this->expectException(QuizAccessDeniedException::class);

        $this->handler(enrolled: [])->handle(new StartAttemptCommand(new Actor(self::STUDENT, Role::Student), BankFixtures::TOPIC, self::FIRST));
    }

    public function test_an_unknown_topic_is_not_found(): void
    {
        $this->expectException(QuizTopicNotFoundException::class);

        $this->start(topicId: '0192f0a0-0000-7000-8000-00000000dead');
    }

    public function test_an_unavailable_topic_cannot_start(): void
    {
        $this->bank->topic = BankFixtures::topic(available: false);

        $this->expectException(QuizTopicUnavailableException::class);

        $this->start();
    }

    public function test_a_topic_without_questions_cannot_start(): void
    {
        $this->bank->questions = [];

        $this->expectException(TopicHasNoQuestionsException::class);

        $this->start();
    }

    public function test_starting_again_returns_the_open_attempt(): void
    {
        $this->start();

        $again = $this->start(self::SECOND);

        self::assertFalse($again->created);
        self::assertSame(self::FIRST, $again->view->id);
        self::assertCount(1, $this->attempts->attempts);
    }

    public function test_resending_the_same_start_returns_it_without_creating_another(): void
    {
        $this->start();

        $again = $this->start();

        self::assertFalse($again->created);
        self::assertCount(1, $this->attempts->attempts);
    }

    public function test_the_same_id_for_another_student_is_an_idempotency_conflict(): void
    {
        $this->start();

        $this->expectException(IdempotencyConflictException::class);

        $this->handler()->handle(new StartAttemptCommand(new Actor('0192f0a0-0000-7000-8000-00000000b002', Role::Student), BankFixtures::TOPIC, self::FIRST));
    }

    public function test_an_open_attempt_is_resumed_even_after_its_topic_was_deactivated(): void
    {
        $this->start();
        $this->bank->topic = BankFixtures::topic(available: false);

        self::assertSame(self::FIRST, $this->start(self::SECOND)->view->id);
    }

    public function test_after_completing_a_quiz_a_new_one_starts(): void
    {
        $this->bank->questions = [BankFixtures::choice(1)];
        $first = $this->attempts->attempts[$this->start()->view->id] ?? self::fail('not stored');
        $first->answer(new AttemptQuestionId($first->questions()[0]->id()->value()), BankFixtures::id(1, 1), AnswerId::random(), new DateTimeImmutable, new DateTimeImmutable);

        $next = $this->start(self::SECOND);

        self::assertTrue($next->created);
        self::assertSame(self::SECOND, $next->view->id);
    }
}
