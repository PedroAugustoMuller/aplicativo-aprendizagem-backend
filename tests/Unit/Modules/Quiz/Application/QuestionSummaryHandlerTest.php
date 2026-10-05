<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Quiz\Application;

use App\Modules\Quiz\Application\Query\GetQuestionSummary\GetQuestionSummaryHandler;
use App\Modules\Quiz\Application\Query\GetQuestionSummary\GetQuestionSummaryQuery;
use App\Modules\Quiz\Application\Service\ProgressAccess;
use App\Modules\Quiz\Domain\Exception\QuizAccessDeniedException;
use App\Modules\Quiz\Domain\Exception\QuizTopicNotFoundException;
use App\Modules\Quiz\Domain\ValueObject\SnapshotOption;
use App\Modules\Quiz\Domain\ValueObject\SummaryAnswer;
use App\Shared\Domain\Auth\Actor;
use App\Shared\Domain\Auth\Role;
use App\Shared\Domain\Contract\BankTopic;
use App\Shared\Domain\Contract\RosterClassroom;
use App\Shared\Domain\Contract\RosterStudent;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Modules\Quiz\Support\BankFixtures;
use Tests\Unit\Modules\Quiz\Support\FakeQuestionBank;
use Tests\Unit\Modules\Quiz\Support\FixedRoster;
use Tests\Unit\Modules\Quiz\Support\InMemoryProgressReader;

final class QuestionSummaryHandlerTest extends TestCase
{
    private const MINE_A = '0192f0a0-0000-7000-8000-00000000e001';

    private const MINE_B = '0192f0a0-0000-7000-8000-00000000e002';

    private const OTHERS = '0192f0a0-0000-7000-8000-00000000e003';

    private const CLOSED = '0192f0a0-0000-7000-8000-00000000e004';

    private const BIOLOGY = '0192f0a0-0000-7000-8000-00000000e005';

    private const TEACHER = '0192f0a0-0000-7000-8000-00000000e101';

    private const OTHER_TEACHER = '0192f0a0-0000-7000-8000-00000000e102';

    private InMemoryProgressReader $reader;

    private Actor $teacher;

    protected function setUp(): void
    {
        $this->reader = new InMemoryProgressReader;
        $this->teacher = new Actor(self::TEACHER, Role::Teacher);
    }

    private static function student(string $id): RosterStudent
    {
        return new RosterStudent($id, "Aluno $id", "aluno.$id");
    }

    /** @param list<RosterClassroom>|null $classrooms */
    private function handler(?array $classrooms = null, ?BankTopic $topic = null): GetQuestionSummaryHandler
    {
        $subject = BankFixtures::SUBJECT;

        return new GetQuestionSummaryHandler($this->reader, new ProgressAccess(new FixedRoster($classrooms ?? [
            new RosterClassroom(self::MINE_A, $subject, true, [self::TEACHER], [self::student('ana'), self::student('bia')]),
            new RosterClassroom(self::MINE_B, $subject, true, [self::TEACHER], [self::student('bia'), self::student('caio')]),
            new RosterClassroom(self::OTHERS, $subject, true, [self::OTHER_TEACHER], [self::student('davi')]),
            new RosterClassroom(self::CLOSED, $subject, false, [self::TEACHER], [self::student('eva')]),
            new RosterClassroom(self::BIOLOGY, 'biology', true, [self::TEACHER], [self::student('fabi')]),
        ])), new FakeQuestionBank($topic ?? BankFixtures::topic(), []));
    }

    private function wrongAnswerBy(string $student): void
    {
        $this->reader->summary[BankFixtures::TOPIC.'|'.BankFixtures::SUBJECT][] = new SummaryAnswer(
            $student, 'q-1', "aq-$student", 'multiple_choice', 'Pergunta?', [new SnapshotOption('o-1', 'Certa'), new SnapshotOption('o-2', 'Errada')],
            'o-1', 'o-2', false, new DateTimeImmutable('2026-10-01 10:00'), new DateTimeImmutable('2026-10-01 09:00'),
        );
    }

    public function test_one_classroom_counts_only_its_students(): void
    {
        foreach (['ana', 'caio', 'davi'] as $student) {
            $this->wrongAnswerBy($student);
        }

        $view = $this->handler()->handle(new GetQuestionSummaryQuery($this->teacher, BankFixtures::TOPIC, self::MINE_A));

        self::assertSame(2, $view->students);
        self::assertCount(1, $view->questions);
        self::assertSame(1, $view->questions[0]->answered);
        self::assertSame([['ana', 'bia']], $this->reader->summaryStudents);
    }

    public function test_a_topic_of_another_subject_than_the_classroom_is_not_found(): void
    {
        $this->expectException(QuizTopicNotFoundException::class);

        $this->handler()->handle(new GetQuestionSummaryQuery($this->teacher, BankFixtures::TOPIC, self::BIOLOGY));
    }

    public function test_an_unknown_topic_is_not_found(): void
    {
        $this->expectException(QuizTopicNotFoundException::class);

        $this->handler()->handle(new GetQuestionSummaryQuery($this->teacher, '0192f0a0-0000-7000-8000-00000000dead'));
    }

    public function test_a_teacher_cannot_read_another_teachers_classroom(): void
    {
        $this->expectException(QuizAccessDeniedException::class);

        $this->handler()->handle(new GetQuestionSummaryQuery($this->teacher, BankFixtures::TOPIC, self::OTHERS));
    }

    public function test_all_my_classrooms_count_each_student_once_and_skip_closed_and_others(): void
    {
        foreach (['ana', 'bia', 'caio', 'davi', 'eva'] as $student) {
            $this->wrongAnswerBy($student);
        }

        $view = $this->handler()->handle(new GetQuestionSummaryQuery($this->teacher, BankFixtures::TOPIC));

        self::assertSame(3, $view->students);
        self::assertSame(3, $view->questions[0]->answered);
        self::assertSame([['ana', 'bia', 'caio']], $this->reader->summaryStudents);
    }

    public function test_an_admin_reads_every_active_classroom_of_the_subject(): void
    {
        $view = $this->handler()->handle(new GetQuestionSummaryQuery(new Actor('admin-1', Role::Admin), BankFixtures::TOPIC));

        self::assertSame(4, $view->students);
    }

    public function test_a_teacher_without_classrooms_in_the_subject_is_denied(): void
    {
        $this->expectException(QuizAccessDeniedException::class);

        $this->handler()->handle(new GetQuestionSummaryQuery(new Actor('nobody', Role::Teacher), BankFixtures::TOPIC));
    }

    public function test_an_admin_without_classrooms_gets_an_empty_summary(): void
    {
        $view = $this->handler(classrooms: [])->handle(new GetQuestionSummaryQuery(new Actor('admin-1', Role::Admin), BankFixtures::TOPIC));

        self::assertSame(0, $view->students);
        self::assertSame([], $view->questions);
    }

    public function test_a_student_is_denied_both_scopes(): void
    {
        $student = new Actor('ana', Role::Student);

        foreach ([self::MINE_A, null] as $classroomId) {
            try {
                $this->handler()->handle(new GetQuestionSummaryQuery($student, BankFixtures::TOPIC, $classroomId));
                self::fail('A student read the summary.');
            } catch (QuizAccessDeniedException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
