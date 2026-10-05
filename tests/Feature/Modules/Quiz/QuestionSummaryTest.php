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

final class QuestionSummaryTest extends TestCase
{
    use ActsAsUsers;
    use RefreshDatabase;

    private string $subjectId;

    private string $topicId;

    private string $classroomId;

    private UserModel $teacher;

    private string $carla;

    private string $ana;

    /** @var array{id: string, options: list<string>} */
    private array $question;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subjectId = $this->makeSubject();
        $this->topicId = $this->makeTopic($this->subjectId, 'Tabela Periódica');
        $this->teacher = $this->makeUser('teacher', 'Bruno');
        $carla = $this->makeUser('student', 'Carla');
        $ana = $this->makeUser('student', 'Ana');
        $this->carla = EloquentAttribute::string($carla->getKey(), 'users.id');
        $this->ana = EloquentAttribute::string($ana->getKey(), 'users.id');
        $this->classroomId = $this->makeClassroom($this->subjectId, teachers: [$this->teacher], students: [$carla, $ana], name: '9º A');
        $this->question = $this->makeQuestionWithOptions($this->topicId, [['Certa', true], ['Errada', false]]);
    }

    /** @return TestResponse<Response> */
    private function getAs(string $uri, ?UserModel $as = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->tokenFor($as ?? $this->teacher))->getJson('/api/v1'.$uri);
    }

    private function classroomUri(?string $classroomId = null, ?string $topicId = null): string
    {
        return '/classrooms/'.($classroomId ?? $this->classroomId).'/topics/'.($topicId ?? $this->topicId).'/question-summary';
    }

    private function subjectUri(?string $topicId = null): string
    {
        return '/topics/'.($topicId ?? $this->topicId).'/question-summary';
    }

    /** One attempt on the shared question: right or wrong. */
    private function answer(string $studentId, bool $correct, string $at, ?string $subjectId = null): void
    {
        $this->makeAnsweredAttempt($studentId, $this->topicId, $subjectId ?? $this->subjectId, [$correct], $at, reuse: [$this->question]);
    }

    private function enrolNew(string $classroomId, string $name): string
    {
        $student = $this->makeUser('student', $name);
        DB::table('classroom_students')->insert(['classroom_id' => $classroomId, 'user_id' => $student->getKey()]);

        return EloquentAttribute::string($student->getKey(), 'users.id');
    }

    public function test_it_counts_each_students_latest_answer_and_the_options_they_chose(): void
    {
        $this->answer($this->carla, false, '2026-10-01 10:00');
        $this->answer($this->carla, true, '2026-10-02 10:00');
        $this->answer($this->ana, false, '2026-10-01 10:00');

        $this->getAs($this->classroomUri())
            ->assertOk()
            ->assertJsonPath('data.students', 2)
            ->assertJsonCount(1, 'data.questions')
            ->assertJsonPath('data.questions.0.question_id', $this->question['id'])
            ->assertJsonPath('data.questions.0.type', 'multiple_choice')
            ->assertJsonPath('data.questions.0.answered', 2)
            ->assertJsonPath('data.questions.0.wrong', 1)
            ->assertJsonPath('data.questions.0.wrong_percent', 50)
            ->assertJsonPath('data.questions.0.correct_option_id', $this->question['options'][0])
            ->assertJsonPath('data.questions.0.options', [
                ['id' => $this->question['options'][0], 'text' => 'Certa', 'chosen' => 1],
                ['id' => $this->question['options'][1], 'text' => 'Errada', 'chosen' => 1],
            ])
            ->assertJsonPath('data.questions.0.other_chosen', 0);
    }

    public function test_a_deleted_question_is_left_out_and_a_deactivated_one_kept(): void
    {
        $deleted = $this->makeAnsweredAttempt($this->carla, $this->topicId, $this->subjectId, [false], '2026-10-01 10:00');
        DB::table('quiz_attempt_questions')->where('id', $deleted['attemptQuestionIds'][0])->update(['question_id' => null]);
        $this->answer($this->carla, false, '2026-10-01 11:00');
        DB::table('questions')->where('id', $this->question['id'])->update(['deactivated_at' => now()]);

        $this->getAs($this->classroomUri())
            ->assertOk()
            ->assertJsonCount(1, 'data.questions')
            ->assertJsonPath('data.questions.0.question_id', $this->question['id']);
    }

    public function test_answers_given_in_another_subject_are_invisible(): void
    {
        $this->answer($this->carla, false, '2026-10-01 10:00', subjectId: $this->makeSubject('Biologia'));

        $this->getAs($this->classroomUri())->assertOk()->assertJsonPath('data.questions', []);
    }

    public function test_access_rules_for_one_classroom(): void
    {
        $biology = $this->makeSubject('Biologia');

        $this->getAs($this->classroomUri(), $this->makeUser('teacher', 'Outro'))->assertForbidden()->assertJsonPath('error.code', 'auth.forbidden');
        $this->getAs($this->classroomUri(), $this->makeUser('admin', 'Ana Admin'))->assertOk();
        $this->getAs($this->classroomUri(), $this->makeUser('student', 'Duda'))->assertForbidden();
        $this->getAs($this->classroomUri(classroomId: '0192f0a0-0000-7000-8000-00000000dead'))->assertNotFound()->assertJsonPath('error.code', 'quiz.classroom_not_found');
        $this->getAs($this->classroomUri(topicId: $this->makeTopic($biology, 'Células')))->assertNotFound()->assertJsonPath('error.code', 'quiz.topic_not_found');
        $this->getAs($this->classroomUri(topicId: '0192f0a0-0000-7000-8000-00000000dead'))->assertNotFound()->assertJsonPath('error.code', 'quiz.topic_not_found');
    }

    public function test_all_my_classrooms_count_a_shared_student_once_and_skip_closed_and_other_teachers_classrooms(): void
    {
        $second = $this->makeClassroom($this->subjectId, teachers: [$this->teacher], name: '9º B');
        DB::table('classroom_students')->insert(['classroom_id' => $second, 'user_id' => $this->carla]);
        $bia = $this->enrolNew($second, 'Bia');
        $closed = $this->makeClassroom($this->subjectId, teachers: [$this->teacher], name: '8º A');
        DB::table('classrooms')->where('id', $closed)->update(['deactivated_at' => now()]);
        $eva = $this->enrolNew($closed, 'Eva');
        $davi = $this->enrolNew($this->makeClassroom($this->subjectId, teachers: [$this->makeUser('teacher', 'Outra')], name: '9º C'), 'Davi');

        foreach ([$this->carla, $this->ana, $bia, $eva, $davi] as $student) {
            $this->answer($student, false, '2026-10-01 10:00');
        }

        $this->getAs($this->subjectUri())
            ->assertOk()
            ->assertJsonPath('data.students', 3)
            ->assertJsonPath('data.questions.0.answered', 3);
        $this->getAs($this->subjectUri(), $this->makeUser('admin', 'Ana Admin'))
            ->assertOk()
            ->assertJsonPath('data.students', 4)
            ->assertJsonPath('data.questions.0.answered', 4);
    }

    public function test_access_rules_for_all_classrooms(): void
    {
        $biology = $this->makeSubject('Biologia');
        $cells = $this->makeTopic($biology, 'Células');

        $this->getAs($this->subjectUri($cells))->assertForbidden()->assertJsonPath('error.code', 'auth.forbidden');
        $this->getAs($this->subjectUri($cells), $this->makeUser('admin', 'Ana Admin'))->assertOk()->assertJsonPath('data', ['students' => 0, 'questions' => []]);
        $this->getAs($this->subjectUri('0192f0a0-0000-7000-8000-00000000dead'))->assertNotFound()->assertJsonPath('error.code', 'quiz.topic_not_found');
        $this->getAs($this->subjectUri(), $this->makeUser('student', 'Duda'))->assertForbidden();
    }

    public function test_both_routes_run_one_quiz_query_however_many_students(): void
    {
        $count = function (string $uri): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getAs($uri)->assertOk();
            // Only the quiz tables: auth queries vary with the guard's cache between requests.
            $n = count(array_filter(DB::getQueryLog(), static fn (array $q): bool => str_contains($q['query'], 'quiz_')));
            DB::disableQueryLog();

            return $n;
        };

        $this->answer($this->carla, true, '2026-10-01 10:00');
        $before = [$count($this->classroomUri()), $count($this->subjectUri())];

        for ($i = 0; $i < 3; $i++) {
            $this->answer($this->enrolNew($this->classroomId, "Aluno $i"), false, '2026-10-01 10:00');
        }

        self::assertSame([1, 1], $before);
        self::assertSame([1, 1], [$count($this->classroomUri()), $count($this->subjectUri())]);
    }
}
