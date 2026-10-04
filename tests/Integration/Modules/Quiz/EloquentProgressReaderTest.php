<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Quiz;

use App\Modules\Quiz\Application\DTO\AnsweredQuestionRow;
use App\Modules\Quiz\Application\DTO\AttemptSummaryRow;
use App\Modules\Quiz\Application\Port\ProgressReader;
use App\Modules\Quiz\Domain\ValueObject\ScoredAnswer;
use App\Shared\Infrastructure\Persistence\EloquentAttribute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Support\ActsAsUsers;
use Tests\TestCase;

final class EloquentProgressReaderTest extends TestCase
{
    use ActsAsUsers;
    use RefreshDatabase;

    private ProgressReader $reader;

    private string $subjectId;

    private string $topicId;

    private string $studentId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reader = $this->app->make(ProgressReader::class);
        $this->subjectId = $this->makeSubject();
        $this->topicId = $this->makeTopic($this->subjectId, 'Tabela Periódica');
        $this->studentId = $this->newStudent('Carla');
    }

    private function newStudent(string $name): string
    {
        return EloquentAttribute::string($this->makeUser('student', $name)->getKey(), 'users.id');
    }

    public function test_it_reads_only_graded_answers_of_the_student_and_topic(): void
    {
        $mine = $this->makeAnsweredAttempt($this->studentId, $this->topicId, $this->subjectId, [true, false], '2026-10-01 10:00');
        $this->makeAnsweredAttempt($this->newStudent('Outra'), $this->topicId, $this->subjectId, [true], '2026-10-01 10:00');
        $this->makeAnsweredAttempt($this->studentId, $this->makeTopic($this->subjectId, 'Átomos', 1), $this->subjectId, [true], '2026-10-01 10:00');
        // An unanswered snapshot row is not a graded answer.
        $this->makeAttempt($this->studentId, $this->topicId, $this->subjectId, $this->makeQuestionWithOptions($this->topicId, [['A', true], ['B', false]]));

        $answers = $this->reader->answersForTopic($this->studentId, $this->topicId, null);

        self::assertSame($mine['attemptQuestionIds'], array_map(static fn (ScoredAnswer $a): string => $a->id, $answers));
        self::assertSame([$mine['attemptId'], $mine['attemptId']], array_map(static fn (ScoredAnswer $a): string => $a->attemptId, $answers));
        self::assertSame([true, false], array_map(static fn (ScoredAnswer $a): bool => $a->correct, $answers));
        self::assertSame('2026-10-01 10:01', $answers[1]->answeredAt->format('Y-m-d H:i'));
    }

    public function test_a_subject_filter_hides_other_subjects(): void
    {
        $this->makeAnsweredAttempt($this->studentId, $this->topicId, $this->subjectId, [true], '2026-10-01 10:00');

        self::assertSame([], $this->reader->answersForTopic($this->studentId, $this->topicId, $this->makeSubject('Biologia')));
        self::assertCount(1, $this->reader->answersForTopic($this->studentId, $this->topicId, $this->subjectId));
    }

    public function test_answers_by_subject_are_grouped_by_topic(): void
    {
        $atoms = $this->makeTopic($this->subjectId, 'Átomos', 1);
        $this->makeAnsweredAttempt($this->studentId, $this->topicId, $this->subjectId, [true, true], '2026-10-01 10:00');
        $this->makeAnsweredAttempt($this->studentId, $atoms, $this->subjectId, [false], '2026-10-01 10:00');

        $grouped = $this->reader->answersBySubject($this->studentId, $this->subjectId);

        self::assertCount(2, $grouped[$this->topicId] ?? []);
        self::assertCount(1, $grouped[$atoms] ?? []);
    }

    public function test_answers_for_students_runs_one_query_whatever_the_class_size(): void
    {
        $ids = [$this->studentId];

        for ($i = 0; $i < 4; $i++) {
            $ids[] = $id = $this->newStudent("Aluno $i");
            $this->makeAnsweredAttempt($id, $this->topicId, $this->subjectId, [true], '2026-10-01 10:00');
        }

        DB::enableQueryLog();
        $grouped = $this->reader->answersForStudents($ids, $this->subjectId);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        self::assertSame(1, $queries);
        self::assertCount(4, $grouped);
        self::assertArrayNotHasKey($this->studentId, $grouped);
        self::assertSame([], $this->reader->answersForStudents([], $this->subjectId));
    }

    public function test_attempt_summaries_are_newest_first_with_their_counts(): void
    {
        $old = $this->makeAnsweredAttempt($this->studentId, $this->topicId, $this->subjectId, [true, false, true], '2026-10-01 10:00');
        $new = $this->makeAnsweredAttempt($this->studentId, $this->topicId, $this->subjectId, [false], '2026-10-02 10:00', complete: false);

        $rows = $this->reader->attemptsForTopic($this->studentId, $this->topicId, null);

        self::assertSame([$new['attemptId'], $old['attemptId']], array_map(static fn (AttemptSummaryRow $r): string => $r->id, $rows));
        self::assertNull($rows[0]->completedAt);
        self::assertSame([3, 3, 2], [$rows[1]->total, $rows[1]->answered, $rows[1]->correct]);
        self::assertSame('2026-10-01T10:00:00+00:00', $rows[1]->startedAt);
        self::assertSame('2026-10-01T10:03:00+00:00', $rows[1]->completedAt);
        self::assertSame([], $this->reader->attemptsForTopic($this->studentId, $this->topicId, $this->makeSubject('Biologia')));
    }

    public function test_answered_questions_skip_rows_whose_bank_question_was_deleted(): void
    {
        $attempt = $this->makeAnsweredAttempt($this->studentId, $this->topicId, $this->subjectId, [false, false], '2026-10-01 10:00');
        DB::table('question_options')->where('question_id', $attempt['questionIds'][0])->delete();
        DB::table('questions')->where('id', $attempt['questionIds'][0])->delete();

        $rows = $this->reader->answeredQuestions($this->studentId, $this->topicId, null);

        self::assertSame([$attempt['questionIds'][1]], array_map(static fn (AnsweredQuestionRow $r): string => $r->questionId, $rows));
        self::assertFalse($rows[0]->correct);
        self::assertSame('Errada', $rows[0]->options[1]->text);
        self::assertSame($rows[0]->options[1]->id, $rows[0]->chosenOptionId);
        self::assertSame('Porque sim.', $rows[0]->explanation);
        self::assertSame('2026-10-01 10:00', $rows[0]->attemptStartedAt->format('Y-m-d H:i'));
    }
}
