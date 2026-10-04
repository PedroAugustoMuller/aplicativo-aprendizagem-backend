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

final class ClassroomProgressTest extends TestCase
{
    use ActsAsUsers;
    use RefreshDatabase;

    private string $subjectId;

    private string $topicId;

    private string $classroomId;

    private UserModel $teacher;

    private string $carla;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subjectId = $this->makeSubject();
        $this->topicId = $this->makeTopic($this->subjectId, 'Tabela Periódica');
        $this->teacher = $this->makeUser('teacher', 'Bruno');
        $student = $this->makeUser('student', 'Carla');
        $this->carla = EloquentAttribute::string($student->getKey(), 'users.id');
        $this->classroomId = $this->makeClassroom($this->subjectId, teachers: [$this->teacher], students: [$student, $this->makeUser('student', 'Ana')]);
    }

    /** @return TestResponse<Response> */
    private function getAs(string $uri, ?UserModel $as = null, ?string $classroomId = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->tokenFor($as ?? $this->teacher))->getJson('/api/v1/classrooms/'.($classroomId ?? $this->classroomId).$uri);
    }

    public function test_the_grid_lists_every_student_by_name_with_their_topics(): void
    {
        $this->makeAnsweredAttempt($this->carla, $this->topicId, $this->subjectId, array_fill(0, 5, true), '2026-10-01 10:00');

        $this->getAs('/quiz-progress')
            ->assertOk()
            ->assertJsonPath('data.students.0.name', 'Ana')
            ->assertJsonPath('data.students.0.topics', [])
            ->assertJsonPath('data.students.1.id', $this->carla)
            ->assertJsonPath('data.students.1.name', 'Carla')
            ->assertJsonPath('data.students.1.topics', [['topic_id' => $this->topicId, 'points' => 50, 'tier' => 'bronze']]);
    }

    public function test_the_grid_does_not_query_once_per_student(): void
    {
        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getAs('/quiz-progress')->assertOk();
            // Only the quiz tables: auth queries vary with the guard's cache between requests.
            $n = count(array_filter(DB::getQueryLog(), static fn (array $q): bool => str_contains($q['query'], 'quiz_')));
            DB::disableQueryLog();

            return $n;
        };

        $one = $count();

        for ($i = 0; $i < 3; $i++) {
            $student = $this->makeUser('student', "Aluno $i");
            DB::table('classroom_students')->insert(['classroom_id' => $this->classroomId, 'user_id' => $student->getKey()]);
            $this->makeAnsweredAttempt(EloquentAttribute::string($student->getKey(), 'users.id'), $this->topicId, $this->subjectId, [true], '2026-10-01 10:00');
        }

        self::assertSame(1, $one);
        self::assertSame(1, $count());
    }

    public function test_staff_read_a_students_topic_history_and_study_list(): void
    {
        $this->makeAnsweredAttempt($this->carla, $this->topicId, $this->subjectId, [true, false], '2026-10-01 10:00');

        $this->getAs("/students/{$this->carla}/topics/{$this->topicId}/quiz-history")
            ->assertOk()->assertJsonPath('data.points', 0)->assertJsonCount(1, 'data.attempts');
        $this->getAs("/students/{$this->carla}/topics/{$this->topicId}/wrong-questions")
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_another_subject_of_the_same_student_is_invisible(): void
    {
        $biology = $this->makeSubject('Biologia');
        $cells = $this->makeTopic($biology, 'Células');
        $attempt = $this->makeAnsweredAttempt($this->carla, $cells, $biology, [false], '2026-10-01 10:00');

        $this->getAs("/students/{$this->carla}/topics/{$cells}/quiz-history")->assertOk()->assertJsonPath('data.attempts', []);
        $this->getAs("/students/{$this->carla}/topics/{$cells}/wrong-questions")->assertOk()->assertJsonPath('data', []);
        $this->getAs("/students/{$this->carla}/quiz-attempts/{$attempt['attemptId']}")->assertNotFound()->assertJsonPath('error.code', 'quiz.attempt_not_found');
    }

    public function test_staff_read_a_completed_attempt_but_not_an_open_one(): void
    {
        $done = $this->makeAnsweredAttempt($this->carla, $this->topicId, $this->subjectId, [true], '2026-10-01 10:00');
        $open = $this->makeAttempt($this->carla, $this->makeTopic($this->subjectId, 'Átomos', 1), $this->subjectId, $this->makeQuestionWithOptions($this->topicId, [['A', true], ['B', false]]));

        $this->getAs("/students/{$this->carla}/quiz-attempts/{$done['attemptId']}")
            ->assertOk()->assertJsonPath('data.id', $done['attemptId'])->assertJsonPath('data.questions.0.result.correct', true);
        $this->getAs("/students/{$this->carla}/quiz-attempts/{$open['attemptId']}")
            ->assertNotFound()->assertJsonPath('error.code', 'quiz.attempt_not_found');
    }

    public function test_access_rules(): void
    {
        $outsider = EloquentAttribute::string($this->makeUser('student', 'Fora')->getKey(), 'users.id');

        $this->getAs('/quiz-progress', $this->makeUser('teacher', 'Outro'))->assertForbidden()->assertJsonPath('error.code', 'auth.forbidden');
        $this->getAs('/quiz-progress', $this->makeUser('admin', 'Ana Admin'))->assertOk();
        $this->getAs("/students/{$outsider}/topics/{$this->topicId}/quiz-history")->assertNotFound()->assertJsonPath('error.code', 'quiz.student_not_found');
        $this->getAs('/quiz-progress', classroomId: '0192f0a0-0000-7000-8000-00000000dead')->assertNotFound()->assertJsonPath('error.code', 'quiz.classroom_not_found');
    }

    public function test_a_deactivated_classroom_stays_readable(): void
    {
        DB::table('classrooms')->where('id', $this->classroomId)->update(['deactivated_at' => now()]);

        $this->getAs('/quiz-progress')->assertOk()->assertJsonCount(2, 'data.students');
    }
}
