<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Quiz\Application;

use App\Modules\Quiz\Application\DTO\AttemptView;
use App\Modules\Quiz\Application\Query\GetAttempt\GetAttemptHandler;
use App\Modules\Quiz\Application\Query\GetAttempt\GetAttemptQuery;
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
use Tests\Unit\Modules\Quiz\Support\InMemoryAttemptRepository;
use Tests\Unit\Modules\Quiz\Support\ReversingShuffler;

final class GetAttemptHandlerTest extends TestCase
{
    private const STUDENT = '0192f0a0-0000-7000-8000-00000000b001';

    private InMemoryAttemptRepository $attempts;

    private Attempt $attempt;

    protected function setUp(): void
    {
        $this->attempts = new InMemoryAttemptRepository;
        $this->attempt = Attempt::start(AttemptId::random(), self::STUDENT, BankFixtures::topic(), BankFixtures::choices(2), new ReversingShuffler, new DateTimeImmutable('2026-10-03 10:00:00+00:00'));
        $this->attempts->add($this->attempt);
    }

    private function get(Actor $actor, ?string $id = null): AttemptView
    {
        return (new GetAttemptHandler($this->attempts, new QuizAccess(new FixedAssignments([]))))
            ->handle(new GetAttemptQuery($actor, $id ?? $this->attempt->id()->value()));
    }

    public function test_the_owner_reads_the_attempt_without_results_before_answering(): void
    {
        $view = $this->get(new Actor(self::STUDENT, Role::Student));

        self::assertSame($this->attempt->id()->value(), $view->id);
        self::assertSame(BankFixtures::TOPIC, $view->topicId);
        self::assertSame('2026-10-03T10:00:00+00:00', $view->startedAt);
        self::assertNull($view->completedAt);
        self::assertNull($view->questions[0]->result);
        self::assertSame(['2-c', '2-b', '2-a'], array_map(static fn ($o): string => $o->text, $view->questions[0]->options));
    }

    public function test_another_students_attempt_is_not_found(): void
    {
        $this->expectException(AttemptNotFoundException::class);

        $this->get(new Actor('0192f0a0-0000-7000-8000-00000000b002', Role::Student));
    }

    public function test_an_unknown_attempt_is_not_found(): void
    {
        $this->expectException(AttemptNotFoundException::class);

        $this->get(new Actor(self::STUDENT, Role::Student), '0192f0a0-0000-7000-8000-00000000dead');
    }

    public function test_staff_cannot_read_attempts(): void
    {
        $this->expectException(QuizAccessDeniedException::class);

        $this->get(new Actor(self::STUDENT, Role::Admin));
    }
}
