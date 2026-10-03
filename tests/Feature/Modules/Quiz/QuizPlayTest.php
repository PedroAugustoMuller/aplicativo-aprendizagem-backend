<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Quiz;

use App\Modules\Identity\Infrastructure\Persistence\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Feature\Support\ActsAsUsers;
use Tests\TestCase;

final class QuizPlayTest extends TestCase
{
    use ActsAsUsers;
    use RefreshDatabase;

    /** Keys that would give the answer away; none may appear on an unanswered question. */
    private const ANSWER_KEYS = ['correct', 'correct_option_id', 'is_correct', 'explanation'];

    private string $subjectId;

    private string $topicId;

    private UserModel $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subjectId = $this->makeSubject();
        $this->topicId = $this->makeTopic($this->subjectId, 'Tabela Periódica');
        $this->student = $this->makeUser('student', 'Carla');
        $this->makeClassroom($this->subjectId, students: [$this->student]);
    }

    /** @return array{id: string, options: list<string>} */
    private function question(?string $topicId = null): array
    {
        return $this->makeQuestionWithOptions($topicId ?? $this->topicId, [['Na', true], ['S', false], ['So', false]]);
    }

    /** @return TestResponse<Response> */
    private function start(?string $id = null, ?string $topicId = null, ?UserModel $as = null): TestResponse
    {
        return $this->withToken($this->tokenFor($as ?? $this->student))
            ->postJson('/api/v1/topics/'.($topicId ?? $this->topicId).'/quiz-attempts', ['id' => $id ?? (string) Str::uuid()]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return TestResponse<Response>
     */
    private function answer(string $attemptId, string $questionId, string $optionId, array $overrides = [], ?UserModel $as = null): TestResponse
    {
        return $this->withToken($this->tokenFor($as ?? $this->student))
            ->postJson("/api/v1/quiz-attempts/$attemptId/answers", $overrides + [
                'answer_id' => (string) Str::uuid(), 'question_id' => $questionId,
                'option_id' => $optionId, 'answered_at' => now()->toIso8601String(),
            ]);
    }

    /**
     * @param  TestResponse<Response>  $response
     * @return array{id: string, questions: list<array{id: string, options: list<array{id: string, text: string}>}>}
     */
    private function attemptOf(TestResponse $response): array
    {
        $data = $response->json('data');
        self::assertIsArray($data);
        $id = $data['id'] ?? null;
        $rawQuestions = $data['questions'] ?? null;
        self::assertIsString($id);
        self::assertIsArray($rawQuestions);
        $questions = [];

        foreach ($rawQuestions as $question) {
            self::assertIsArray($question);
            $questionId = $question['id'] ?? null;
            $rawOptions = $question['options'] ?? null;
            self::assertIsString($questionId);
            self::assertIsArray($rawOptions);
            $options = [];

            foreach ($rawOptions as $option) {
                self::assertIsArray($option);
                $optionId = $option['id'] ?? null;
                $text = $option['text'] ?? null;
                self::assertIsString($optionId);
                self::assertIsString($text);
                $options[] = ['id' => $optionId, 'text' => $text];
            }

            $questions[] = ['id' => $questionId, 'options' => $options];
        }

        return ['id' => $id, 'questions' => $questions];
    }

    /** @param array{id: string, options: list<array{id: string, text: string}>} $question */
    private function optionNamed(array $question, string $text): string
    {
        foreach ($question['options'] as $option) {
            if ($option['text'] === $text) {
                return $option['id'];
            }
        }

        self::fail("No option \"$text\".");
    }

    /** @param array<mixed> $node */
    private function assertNoAnswerKeys(array $node, string $path = 'data'): void
    {
        foreach ($node as $key => $value) {
            self::assertNotContains($key, self::ANSWER_KEYS, "$path.$key gives the answer away");

            if (is_array($value)) {
                $this->assertNoAnswerKeys($value, "$path.$key");
            }
        }
    }

    public function test_a_student_starts_a_quiz_of_ten_without_the_answer_key(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $this->question();
        }
        $id = (string) Str::uuid();

        $response = $this->start($id)
            ->assertStatus(201)
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.topic_id', $this->topicId)
            ->assertJsonPath('data.completed_at', null)
            ->assertJsonPath('data.score', ['total' => 10, 'answered' => 0, 'correct' => 0])
            ->assertJsonCount(10, 'data.questions')
            ->assertJsonCount(3, 'data.questions.0.options')
            ->assertJsonPath('data.questions.0.result', null);

        // The questions only: `score.correct` is a count, not a hint.
        $questions = $response->json('data.questions');
        self::assertIsArray($questions);
        $this->assertNoAnswerKeys($questions, 'data.questions');
    }

    public function test_starting_again_returns_the_open_attempt(): void
    {
        $this->question();
        $first = (string) Str::uuid();
        $this->start($first)->assertStatus(201);

        $this->start()->assertStatus(200)->assertJsonPath('data.id', $first);
        $this->start($first)->assertStatus(200)->assertJsonPath('data.id', $first);
        self::assertSame(1, DB::table('quiz_attempts')->count());
    }

    public function test_the_same_id_on_another_topic_is_an_idempotency_conflict(): void
    {
        $this->question();
        $other = $this->makeTopic($this->subjectId, 'Ligações');
        $this->question($other);
        $id = (string) Str::uuid();
        $this->start($id)->assertStatus(201);

        $this->start($id, $other)->assertStatus(409)->assertJsonPath('error.code', 'system.idempotency_conflict');
    }

    public function test_answering_reveals_the_result_and_the_explanation(): void
    {
        $question = $this->question();
        $this->question();
        $attempt = $this->attemptOf($this->start());
        $target = $attempt['questions'][0];
        $chosen = $this->optionNamed($target, 'S');

        $response = $this->answer($attempt['id'], $target['id'], $chosen)
            ->assertOk()
            ->assertJsonPath('data.question_id', $target['id'])
            ->assertJsonPath('data.option_id', $chosen)
            ->assertJsonPath('data.correct', false)
            ->assertJsonPath('data.explanation', 'Explicação.')
            ->assertJsonPath('data.score', ['total' => 2, 'answered' => 1, 'correct' => 0])
            ->assertJsonPath('data.completed', false);

        self::assertSame($this->optionNamed($target, 'Na'), $response->json('data.correct_option_id'));
        self::assertContains($question['id'], DB::table('quiz_attempt_questions')->pluck('question_id')->all());
    }

    public function test_a_resent_answer_returns_the_same_result_and_another_one_is_refused(): void
    {
        $this->question();
        $this->question();
        $attempt = $this->attemptOf($this->start());
        $target = $attempt['questions'][0];
        $body = ['answer_id' => (string) Str::uuid()];

        $first = $this->answer($attempt['id'], $target['id'], $target['options'][0]['id'], $body)->assertOk();
        $this->answer($attempt['id'], $target['id'], $target['options'][0]['id'], $body)
            ->assertOk()
            ->assertJsonPath('data.correct', $first->json('data.correct'));

        $this->answer($attempt['id'], $target['id'], $target['options'][1]['id'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'quiz.question.already_answered');
        self::assertSame(1, DB::table('quiz_attempt_questions')->whereNotNull('answer_id')->count());
    }

    public function test_an_option_from_another_question_is_refused(): void
    {
        $this->question();
        $this->question();
        $attempt = $this->attemptOf($this->start());

        $this->answer($attempt['id'], $attempt['questions'][0]['id'], $attempt['questions'][1]['options'][0]['id'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'quiz.answer.invalid_option');
    }

    public function test_the_last_answer_completes_the_quiz_and_a_new_one_can_start(): void
    {
        $this->question();
        $first = $this->attemptOf($this->start());

        $this->answer($first['id'], $first['questions'][0]['id'], $first['questions'][0]['options'][0]['id'])
            ->assertOk()
            ->assertJsonPath('data.completed', true);

        $this->withToken($this->tokenFor($this->student))->getJson('/api/v1/quiz-attempts/'.$first['id'])
            ->assertOk()
            ->assertJsonPath('data.score.answered', 1)
            ->assertJsonPath('data.questions.0.result.option_id', $first['questions'][0]['options'][0]['id']);
        self::assertNotNull($this->start()->assertStatus(201)->json('data.id'));
    }

    public function test_another_students_attempt_is_not_found(): void
    {
        $this->question();
        $attempt = $this->attemptOf($this->start());
        $other = $this->makeUser('student', 'Diego');
        $this->makeClassroom($this->subjectId, students: [$other], name: 'Química 2');
        // The guard caches the user across requests in one test: switch accounts for real.
        $this->app['auth']->forgetGuards();

        $this->withToken($this->tokenFor($other))->getJson('/api/v1/quiz-attempts/'.$attempt['id'])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'quiz.attempt_not_found');
        $this->answer($attempt['id'], $attempt['questions'][0]['id'], $attempt['questions'][0]['options'][0]['id'], [], $other)
            ->assertStatus(404);
    }

    public function test_a_student_not_enrolled_is_forbidden(): void
    {
        $this->question();
        $outsider = $this->makeUser('student', 'Eva');

        $this->start(as: $outsider)->assertStatus(403)->assertJsonPath('error.code', 'auth.forbidden');
    }

    public function test_a_topic_without_questions_or_unknown_is_refused(): void
    {
        $this->start()->assertStatus(409)->assertJsonPath('error.code', 'quiz.topic.no_questions');
        $this->start(topicId: (string) Str::uuid())->assertStatus(404)->assertJsonPath('error.code', 'quiz.topic_not_found');
    }

    public function test_a_deactivated_topic_cannot_start_but_an_open_attempt_can_still_be_answered(): void
    {
        $this->question();
        $attempt = $this->attemptOf($this->start());
        DB::table('topics')->where('id', $this->topicId)->update(['deactivated_at' => now()]);

        $this->answer($attempt['id'], $attempt['questions'][0]['id'], $attempt['questions'][0]['options'][0]['id'])->assertOk();
        $this->start()->assertStatus(409)->assertJsonPath('error.code', 'quiz.topic.unavailable');
    }

    public function test_an_answer_with_a_wrong_clock_stays_within_the_attempt(): void
    {
        $this->question();
        $attempt = $this->attemptOf($this->start());

        $this->answer($attempt['id'], $attempt['questions'][0]['id'], $attempt['questions'][0]['options'][0]['id'], ['answered_at' => '2000-01-01T00:00:00+00:00'])->assertOk();

        $row = DB::table('quiz_attempt_questions')->where('attempt_id', $attempt['id'])->first();
        $started = DB::table('quiz_attempts')->where('id', $attempt['id'])->value('started_at');
        self::assertNotNull($row);
        self::assertSame($started, $row->answered_at);
    }

    public function test_an_answer_body_is_validated(): void
    {
        $this->question();
        $attempt = $this->attemptOf($this->start());

        $this->answer($attempt['id'], $attempt['questions'][0]['id'], 'not-a-uuid', ['answered_at' => null])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation.failed');
    }

    public function test_ten_answers_in_a_row_are_not_throttled(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->question();
        }
        $token = $this->tokenFor($this->student);
        $attempt = $this->attemptOf($this->withToken($token)->postJson("/api/v1/topics/{$this->topicId}/quiz-attempts", ['id' => (string) Str::uuid()]));

        foreach ($attempt['questions'] as $question) {
            $this->withToken($token)->postJson("/api/v1/quiz-attempts/{$attempt['id']}/answers", [
                'answer_id' => (string) Str::uuid(), 'question_id' => $question['id'],
                'option_id' => $question['options'][0]['id'], 'answered_at' => now()->toIso8601String(),
            ])->assertOk();
        }

        self::assertSame(10, DB::table('quiz_attempt_questions')->whereNotNull('answer_id')->count());
    }
}
