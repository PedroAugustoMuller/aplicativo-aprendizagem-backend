<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Quiz\Application;

use App\Modules\Quiz\Application\DTO\AnsweredQuestionRow;
use App\Modules\Quiz\Application\DTO\AttemptSummaryRow;
use App\Modules\Quiz\Application\DTO\AttemptSummaryView;
use App\Modules\Quiz\Application\DTO\WrongQuestionView;
use App\Modules\Quiz\Application\Query\GetSubjectProgress\GetSubjectProgressHandler;
use App\Modules\Quiz\Application\Query\GetSubjectProgress\GetSubjectProgressQuery;
use App\Modules\Quiz\Application\Query\GetTopicHistory\GetTopicHistoryHandler;
use App\Modules\Quiz\Application\Query\GetTopicHistory\GetTopicHistoryQuery;
use App\Modules\Quiz\Application\Query\GetWrongQuestions\GetWrongQuestionsHandler;
use App\Modules\Quiz\Application\Query\GetWrongQuestions\GetWrongQuestionsQuery;
use App\Modules\Quiz\Application\Service\ProgressAccess;
use App\Modules\Quiz\Domain\Exception\QuizAccessDeniedException;
use App\Modules\Quiz\Domain\ValueObject\ScoredAnswer;
use App\Modules\Quiz\Domain\ValueObject\SnapshotOption;
use App\Modules\Quiz\Domain\ValueObject\Tier;
use App\Shared\Domain\Auth\Actor;
use App\Shared\Domain\Auth\Role;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Modules\Quiz\Support\FixedRoster;
use Tests\Unit\Modules\Quiz\Support\InMemoryProgressReader;

final class ProgressHandlersTest extends TestCase
{
    private const STUDENT = '0192f0a0-0000-7000-8000-00000000c001';

    private const SUBJECT = '0192f0a0-0000-7000-8000-00000000c101';

    private const TOPIC = '0192f0a0-0000-7000-8000-00000000c201';

    private const KEY = self::STUDENT.'|'.self::TOPIC.'|'.self::SUBJECT;

    private InMemoryProgressReader $reader;

    private Actor $student;

    protected function setUp(): void
    {
        $this->reader = new InMemoryProgressReader;
        $this->student = new Actor(self::STUDENT, Role::Student);
    }

    private function access(): ProgressAccess
    {
        return new ProgressAccess(new FixedRoster([]));
    }

    /** @param list<bool> $results */
    private function answered(string $attemptId, string $start, array $results): void
    {
        foreach ($results as $position => $correct) {
            $this->reader->answers[self::KEY][] = new ScoredAnswer($attemptId, "$attemptId-$position", $position, $correct, (new DateTimeImmutable($start))->modify("+$position minutes"));
        }
    }

    public function test_subject_progress_lists_only_topics_with_answers(): void
    {
        $this->answered('a', '2026-10-01 10:00', array_fill(0, 6, true));

        $views = (new GetSubjectProgressHandler($this->reader, $this->access()))->handle(new GetSubjectProgressQuery($this->student, self::SUBJECT));

        self::assertCount(1, $views);
        self::assertSame([self::TOPIC, 60, Tier::Bronze, Tier::Silver], [$views[0]->topicId, $views[0]->points, $views[0]->tier, $views[0]->nextTier]);
        self::assertSame(['answersBySubject'], $this->reader->calls);
    }

    public function test_history_carries_points_and_tiers_per_attempt(): void
    {
        $this->answered('a', '2026-10-01 10:00', array_fill(0, 5, true));
        $this->answered('b', '2026-10-02 10:00', [false, false, false]);
        $this->reader->attempts[self::KEY] = [
            new AttemptSummaryRow('b', '2026-10-02T10:00:00+00:00', '2026-10-02T10:03:00+00:00', 3, 3, 0),
            new AttemptSummaryRow('a', '2026-10-01T10:00:00+00:00', '2026-10-01T10:05:00+00:00', 5, 5, 5),
        ];

        $view = (new GetTopicHistoryHandler($this->reader, $this->access()))->handle(new GetTopicHistoryQuery($this->student, self::TOPIC));

        self::assertSame([20, Tier::Iron, Tier::Bronze], [$view->points, $view->tier, $view->nextTier]);
        self::assertSame(['b', 'a'], array_map(static fn (AttemptSummaryView $a): string => $a->id, $view->attempts));
        self::assertSame([50, 20, -30, Tier::Bronze, Tier::Iron], [$view->attempts[0]->pointsBefore, $view->attempts[0]->pointsAfter, $view->attempts[0]->pointsChange(), $view->attempts[0]->tierBefore, $view->attempts[0]->tierAfter]);
        self::assertSame([0, 50, 50, Tier::Iron, Tier::Bronze], [$view->attempts[1]->pointsBefore, $view->attempts[1]->pointsAfter, $view->attempts[1]->pointsChange(), $view->attempts[1]->tierBefore, $view->attempts[1]->tierAfter]);
    }

    private function row(string $questionId, bool $correct, string $answeredAt, string $startedAt = '2026-10-01 09:00'): AnsweredQuestionRow
    {
        return new AnsweredQuestionRow(
            $questionId, 'multiple_choice', "Enunciado $questionId $answeredAt",
            [new SnapshotOption("$questionId-r", 'Certa'), new SnapshotOption("$questionId-w", 'Errada')],
            $correct ? "$questionId-r" : "$questionId-w", "$questionId-r", 'Explicação', $correct,
            new DateTimeImmutable($answeredAt.'+00:00'), new DateTimeImmutable($startedAt.'+00:00'),
        );
    }

    public function test_the_study_list_keeps_questions_whose_latest_answer_is_wrong(): void
    {
        $this->reader->questions[self::KEY] = [
            $this->row('q1', false, '2026-10-01 10:00'),
            $this->row('q1', true, '2026-10-02 10:00'),
            $this->row('q2', true, '2026-10-01 10:01'),
            $this->row('q2', false, '2026-10-02 10:01'),
            $this->row('q3', false, '2026-10-01 10:02'),
        ];

        $views = (new GetWrongQuestionsHandler($this->reader, $this->access()))->handle(new GetWrongQuestionsQuery($this->student, self::TOPIC));

        self::assertSame(['q2', 'q3'], array_map(static fn (WrongQuestionView $v): string => $v->questionId, $views));
        self::assertSame('Enunciado q2 2026-10-02 10:01', $views[0]->statement, 'uses the snapshot of the latest answer');
        self::assertSame('q2-w', $views[0]->chosenOptionId);
        self::assertSame('2026-10-02T10:01:00+00:00', $views[0]->answeredAt);
    }

    public function test_equal_answer_times_fall_back_to_the_newer_attempt(): void
    {
        $this->reader->questions[self::KEY] = [
            $this->row('q1', false, '2026-10-01 10:00', '2026-10-01 10:00'),
            $this->row('q1', true, '2026-10-01 10:00', '2026-10-01 09:00'),
        ];

        $views = (new GetWrongQuestionsHandler($this->reader, $this->access()))->handle(new GetWrongQuestionsQuery($this->student, self::TOPIC));

        self::assertSame(['q1'], array_map(static fn (WrongQuestionView $v): string => $v->questionId, $views));
    }

    public function test_staff_cannot_read_own_progress(): void
    {
        $this->expectException(QuizAccessDeniedException::class);

        (new GetTopicHistoryHandler($this->reader, $this->access()))->handle(new GetTopicHistoryQuery(new Actor(self::STUDENT, Role::Teacher), self::TOPIC));
    }

    public function test_staff_cannot_read_subject_progress_as_a_student(): void
    {
        $this->expectException(QuizAccessDeniedException::class);

        (new GetSubjectProgressHandler($this->reader, $this->access()))->handle(new GetSubjectProgressQuery(new Actor(self::STUDENT, Role::Admin), self::SUBJECT));
    }
}
