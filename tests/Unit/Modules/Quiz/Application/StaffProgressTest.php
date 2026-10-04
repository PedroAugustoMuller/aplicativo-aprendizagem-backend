<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Quiz\Application;

use App\Modules\Quiz\Application\DTO\AttemptView;
use App\Modules\Quiz\Application\DTO\TopicProgressView;
use App\Modules\Quiz\Application\Query\GetClassroomProgress\GetClassroomProgressHandler;
use App\Modules\Quiz\Application\Query\GetClassroomProgress\GetClassroomProgressQuery;
use App\Modules\Quiz\Application\Query\GetStudentAttempt\GetStudentAttemptHandler;
use App\Modules\Quiz\Application\Query\GetStudentAttempt\GetStudentAttemptQuery;
use App\Modules\Quiz\Application\Query\GetTopicHistory\GetTopicHistoryHandler;
use App\Modules\Quiz\Application\Query\GetTopicHistory\GetTopicHistoryQuery;
use App\Modules\Quiz\Application\Service\ProgressAccess;
use App\Modules\Quiz\Domain\Entity\Attempt;
use App\Modules\Quiz\Domain\Exception\AttemptNotFoundException;
use App\Modules\Quiz\Domain\Exception\QuizAccessDeniedException;
use App\Modules\Quiz\Domain\ValueObject\AnswerId;
use App\Modules\Quiz\Domain\ValueObject\AttemptId;
use App\Modules\Quiz\Domain\ValueObject\ScoredAnswer;
use App\Modules\Quiz\Domain\ValueObject\Tier;
use App\Shared\Domain\Auth\Actor;
use App\Shared\Domain\Auth\Role;
use App\Shared\Domain\Contract\BankTopic;
use App\Shared\Domain\Contract\RosterClassroom;
use App\Shared\Domain\Contract\RosterStudent;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Modules\Quiz\Support\BankFixtures;
use Tests\Unit\Modules\Quiz\Support\FixedRoster;
use Tests\Unit\Modules\Quiz\Support\InMemoryAttemptRepository;
use Tests\Unit\Modules\Quiz\Support\InMemoryProgressReader;
use Tests\Unit\Modules\Quiz\Support\ReversingShuffler;

final class StaffProgressTest extends TestCase
{
    private const CLASSROOM = '0192f0a0-0000-7000-8000-00000000d001';

    private const TEACHER = '0192f0a0-0000-7000-8000-00000000d101';

    private const STUDENT = '0192f0a0-0000-7000-8000-00000000d201';

    private const ANA = '0192f0a0-0000-7000-8000-00000000d202';

    private InMemoryProgressReader $reader;

    private ProgressAccess $access;

    private Actor $teacher;

    protected function setUp(): void
    {
        $this->reader = new InMemoryProgressReader;
        $this->teacher = new Actor(self::TEACHER, Role::Teacher);
        $this->access = new ProgressAccess(new FixedRoster([
            new RosterClassroom(self::CLASSROOM, BankFixtures::SUBJECT, true, [self::TEACHER], [
                new RosterStudent(self::ANA, 'Ana', 'ana.lima'),
                new RosterStudent(self::STUDENT, 'Carla', 'carla.dias'),
            ]),
        ]));
    }

    public function test_the_classroom_grid_lists_every_student_with_their_topics(): void
    {
        $this->reader->answers[self::STUDENT.'|t-1|'.BankFixtures::SUBJECT] = [new ScoredAnswer('a', 'a-0', 0, true, new DateTimeImmutable('2026-10-01'))];
        $this->reader->answers[self::STUDENT.'|t-1|other-subject'] = [new ScoredAnswer('b', 'b-0', 0, true, new DateTimeImmutable('2026-10-01'))];

        $view = (new GetClassroomProgressHandler($this->reader, $this->access))->handle(new GetClassroomProgressQuery($this->teacher, self::CLASSROOM));

        self::assertSame(['Ana', 'Carla'], array_map(static fn ($s): string => $s->name, $view->students));
        self::assertSame([], $view->students[0]->topics);
        self::assertSame('carla.dias', $view->students[1]->username);
        self::assertSame([['t-1', 10, Tier::Iron]], array_map(static fn (TopicProgressView $t): array => [$t->topicId, $t->points, $t->tier], $view->students[1]->topics));
        self::assertSame(['answersForStudents'], $this->reader->calls);
    }

    public function test_the_grid_is_staff_only(): void
    {
        $this->expectException(QuizAccessDeniedException::class);

        (new GetClassroomProgressHandler($this->reader, $this->access))->handle(new GetClassroomProgressQuery(new Actor(self::STUDENT, Role::Student), self::CLASSROOM));
    }

    public function test_staff_history_is_limited_to_the_classroom_subject(): void
    {
        $this->reader->answers[self::STUDENT.'|t-1|'.BankFixtures::SUBJECT] = [new ScoredAnswer('a', 'a-0', 0, true, new DateTimeImmutable('2026-10-01'))];
        $this->reader->answers[self::STUDENT.'|t-1|other-subject'] = [new ScoredAnswer('b', 'b-0', 0, true, new DateTimeImmutable('2026-10-01'))];

        $view = (new GetTopicHistoryHandler($this->reader, $this->access))->handle(new GetTopicHistoryQuery($this->teacher, 't-1', self::CLASSROOM, self::STUDENT));

        self::assertSame(10, $view->points);
    }

    private function attempt(bool $complete, string $student = self::STUDENT, ?BankTopic $topic = null): Attempt
    {
        $attempt = Attempt::start(AttemptId::random(), $student, $topic ?? BankFixtures::topic(), BankFixtures::choices(1), new ReversingShuffler, new DateTimeImmutable('2026-10-03 10:00:00+00:00'));

        if ($complete) {
            $question = $attempt->questions()[0];
            $attempt->answer($question->id(), $question->options()[0]->id, AnswerId::random(), new DateTimeImmutable('2026-10-03 10:01:00+00:00'), new DateTimeImmutable('2026-10-03 10:02:00+00:00'));
        }

        return $attempt;
    }

    private function readAttempt(Attempt $attempt): AttemptView
    {
        $repository = new InMemoryAttemptRepository;
        $repository->add($attempt);

        return (new GetStudentAttemptHandler($repository, $this->access))->handle(new GetStudentAttemptQuery($this->teacher, self::CLASSROOM, self::STUDENT, $attempt->id()->value()));
    }

    public function test_staff_read_a_completed_attempt_of_the_student(): void
    {
        $attempt = $this->attempt(complete: true);

        $view = $this->readAttempt($attempt);

        self::assertSame($attempt->id()->value(), $view->id);
        self::assertNotNull($view->questions[0]->result);
    }

    public function test_an_open_attempt_is_not_found_for_staff(): void
    {
        $this->expectException(AttemptNotFoundException::class);

        $this->readAttempt($this->attempt(complete: false));
    }

    public function test_another_students_attempt_is_not_found_for_staff(): void
    {
        $this->expectException(AttemptNotFoundException::class);

        $this->readAttempt($this->attempt(complete: true, student: self::ANA));
    }

    public function test_an_attempt_of_another_subject_is_not_found_for_staff(): void
    {
        $this->expectException(AttemptNotFoundException::class);

        $this->readAttempt($this->attempt(complete: true, topic: new BankTopic(BankFixtures::TOPIC, '0192f0a0-0000-7000-8000-00000000eeee', true)));
    }

    public function test_an_unknown_attempt_is_not_found_for_staff(): void
    {
        $this->expectException(AttemptNotFoundException::class);

        (new GetStudentAttemptHandler(new InMemoryAttemptRepository, $this->access))->handle(new GetStudentAttemptQuery($this->teacher, self::CLASSROOM, self::STUDENT, '0192f0a0-0000-7000-8000-00000000dead'));
    }
}
