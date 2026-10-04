<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Quiz;

use App\Modules\Identity\Infrastructure\Persistence\UserModel;
use App\Shared\Infrastructure\Persistence\EloquentAttribute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Feature\Support\ActsAsUsers;
use Tests\TestCase;

final class StudentProgressTest extends TestCase
{
    use ActsAsUsers;
    use RefreshDatabase;

    private string $subjectId;

    private string $topicId;

    private UserModel $student;

    private string $studentId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subjectId = $this->makeSubject();
        $this->topicId = $this->makeTopic($this->subjectId, 'Tabela Periódica');
        $this->student = $this->makeUser('student', 'Carla');
        $this->studentId = EloquentAttribute::string($this->student->getKey(), 'users.id');
        $this->makeClassroom($this->subjectId, students: [$this->student]);
    }

    /** @return TestResponse<Response> */
    private function getAs(string $uri, ?UserModel $as = null): TestResponse
    {
        return $this->withToken($this->tokenFor($as ?? $this->student))->getJson('/api/v1'.$uri);
    }

    public function test_an_empty_topic_is_zero_points_in_iron(): void
    {
        $this->getAs("/topics/{$this->topicId}/quiz-history")
            ->assertOk()
            ->assertExactJson(['data' => ['points' => 0, 'tier' => 'iron', 'next_tier' => ['tier' => 'bronze', 'points' => 50], 'attempts' => []]]);
        $this->getAs("/subjects/{$this->subjectId}/quiz-progress")->assertOk()->assertExactJson(['data' => []]);
        $this->getAs("/topics/{$this->topicId}/wrong-questions")->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_history_shows_each_quiz_with_its_points_and_tiers(): void
    {
        $first = $this->makeAnsweredAttempt($this->studentId, $this->topicId, $this->subjectId, array_fill(0, 6, true), '2026-10-01 10:00');
        $second = $this->makeAnsweredAttempt($this->studentId, $this->topicId, $this->subjectId, array_fill(0, 7, false), '2026-10-02 10:00');

        $this->getAs("/topics/{$this->topicId}/quiz-history")
            ->assertOk()
            ->assertJsonPath('data.points', 0)
            ->assertJsonPath('data.tier', 'iron')
            ->assertJsonPath('data.attempts.0.id', $second['attemptId'])
            ->assertJsonPath('data.attempts.0.started_at', '2026-10-02T10:00:00+00:00')
            ->assertJsonPath('data.attempts.0.points_before', 60)
            ->assertJsonPath('data.attempts.0.points_after', 0)
            ->assertJsonPath('data.attempts.0.points_change', -60)
            ->assertJsonPath('data.attempts.0.tier_before', 'bronze')
            ->assertJsonPath('data.attempts.0.tier_after', 'iron')
            ->assertJsonPath('data.attempts.1.id', $first['attemptId'])
            ->assertJsonPath('data.attempts.1.correct', 6)
            ->assertJsonPath('data.attempts.1.answered', 6)
            ->assertJsonPath('data.attempts.1.total', 6)
            ->assertJsonPath('data.attempts.1.points_change', 60);
    }

    public function test_a_wrong_only_quiz_at_zero_points_changes_nothing(): void
    {
        $this->makeAnsweredAttempt($this->studentId, $this->topicId, $this->subjectId, array_fill(0, 10, false), '2026-10-01 10:00');

        $this->getAs("/topics/{$this->topicId}/quiz-history")
            ->assertJsonPath('data.points', 0)
            ->assertJsonPath('data.attempts.0.points_change', 0);
    }

    public function test_a_late_offline_answer_lands_in_place(): void
    {
        // Right at 10:00, wrong at 10:01 → 0. A later sync brings a 09:00 right answer of an
        // older attempt: 09:00 +10, 10:00 +10, 10:01 -10 → 10.
        $this->makeAnsweredAttempt($this->studentId, $this->topicId, $this->subjectId, [true, false], '2026-10-02 10:00');
        $this->getAs("/topics/{$this->topicId}/quiz-history")->assertJsonPath('data.points', 0);

        $this->makeAnsweredAttempt($this->studentId, $this->topicId, $this->subjectId, [true], '2026-10-02 09:00');

        $this->getAs("/topics/{$this->topicId}/quiz-history")->assertJsonPath('data.points', 10);
    }

    public function test_an_open_quiz_is_listed_without_a_completion_time(): void
    {
        $open = $this->makeAnsweredAttempt($this->studentId, $this->topicId, $this->subjectId, [true], '2026-10-01 10:00', complete: false);

        $this->getAs("/topics/{$this->topicId}/quiz-history")
            ->assertJsonPath('data.attempts.0.id', $open['attemptId'])
            ->assertJsonPath('data.attempts.0.completed_at', null);
    }

    public function test_subject_progress_filters_by_subject(): void
    {
        $biology = $this->makeSubject('Biologia');
        $cells = $this->makeTopic($biology, 'Células');
        $this->makeAnsweredAttempt($this->studentId, $this->topicId, $this->subjectId, array_fill(0, 5, true), '2026-10-01 10:00');
        $this->makeAnsweredAttempt($this->studentId, $cells, $biology, [true], '2026-10-01 10:00');

        $this->getAs("/subjects/{$this->subjectId}/quiz-progress")
            ->assertOk()
            ->assertExactJson(['data' => [['topic_id' => $this->topicId, 'points' => 50, 'tier' => 'bronze', 'next_tier' => ['tier' => 'silver', 'points' => 150]]]]);
    }

    public function test_subject_progress_does_not_query_once_per_topic(): void
    {
        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getAs("/subjects/{$this->subjectId}/quiz-progress")->assertOk();
            // Only the quiz tables: auth queries vary with the guard's cache between requests.
            $n = count(array_filter(DB::getQueryLog(), static fn (array $q): bool => str_contains($q['query'], 'quiz_')));
            DB::disableQueryLog();

            return $n;
        };

        $this->makeAnsweredAttempt($this->studentId, $this->topicId, $this->subjectId, [true], '2026-10-01 10:00');
        $one = $count();

        for ($i = 1; $i <= 3; $i++) {
            $this->makeAnsweredAttempt($this->studentId, $this->makeTopic($this->subjectId, "Conteúdo $i", $i), $this->subjectId, [true], '2026-10-01 10:00');
        }

        self::assertSame(1, $one);
        self::assertSame(1, $count());
    }

    public function test_the_study_list_keeps_the_latest_wrong_answers_only(): void
    {
        $first = $this->makeAnsweredAttempt($this->studentId, $this->topicId, $this->subjectId, [false, false, true], '2026-10-01 10:00');
        $bank = array_map(fn (string $id): array => ['id' => $id, 'options' => $this->optionIdsOf($id)], $first['questionIds']);
        // Second quiz: q0 now right (leaves), q1 still wrong (stays), q2 now wrong (enters).
        $this->makeAnsweredAttempt($this->studentId, $this->topicId, $this->subjectId, [true, false, false], '2026-10-02 10:00', reuse: $bank);

        $response = $this->getAs("/topics/{$this->topicId}/wrong-questions")->assertOk()->assertJsonCount(2, 'data');

        $data = $response->json('data');
        self::assertIsArray($data);
        self::assertEqualsCanonicalizing([$first['questionIds'][1], $first['questionIds'][2]], array_column($data, 'question_id'));
        $response->assertJsonPath('data.0.options.1.text', 'Errada')
            ->assertJsonPath('data.0.explanation', 'Porque sim.')
            ->assertJsonPath('data.0.type', 'multiple_choice');
    }

    public function test_the_study_list_leaves_out_deleted_questions_and_keeps_deactivated_ones(): void
    {
        $attempt = $this->makeAnsweredAttempt($this->studentId, $this->topicId, $this->subjectId, [false, false], '2026-10-01 10:00');
        DB::table('question_options')->where('question_id', $attempt['questionIds'][0])->delete();
        DB::table('questions')->where('id', $attempt['questionIds'][0])->delete();
        DB::table('questions')->where('id', $attempt['questionIds'][1])->update(['deactivated_at' => now()]);

        $this->getAs("/topics/{$this->topicId}/wrong-questions")
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.question_id', $attempt['questionIds'][1]);
    }

    public function test_each_student_sees_only_their_own_progress(): void
    {
        $this->makeAnsweredAttempt($this->studentId, $this->topicId, $this->subjectId, array_fill(0, 5, true), '2026-10-01 10:00');
        $other = $this->makeUser('student', 'Outra');
        $this->makeClassroom($this->subjectId, students: [$other], name: 'Química 2');

        $this->getAs("/topics/{$this->topicId}/quiz-history", $other)->assertJsonPath('data.points', 0)->assertJsonPath('data.attempts', []);
    }

    public function test_staff_get_403_and_guests_401(): void
    {
        $this->getAs("/topics/{$this->topicId}/quiz-history", $this->makeUser('teacher', 'Bruno'))->assertForbidden()->assertJsonPath('error.code', 'auth.forbidden');
        $this->app['auth']->forgetGuards();
        $this->flushHeaders()->getJson("/api/v1/topics/{$this->topicId}/quiz-history")->assertUnauthorized();
        $this->getAs('/topics/not-a-uuid/quiz-history')->assertNotFound();
    }

    /** @return list<string> */
    private function optionIdsOf(string $questionId): array
    {
        /** @var list<string> $ids */
        $ids = DB::table('question_options')->where('question_id', $questionId)->orderBy('position')->pluck('id')->all();

        return $ids;
    }
}
