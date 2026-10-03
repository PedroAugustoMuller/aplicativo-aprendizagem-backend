<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Content;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Support\ActsAsUsers;
use Tests\TestCase;

final class QuestionAuthoringTest extends TestCase
{
    use ActsAsUsers;
    use RefreshDatabase;

    private string $subjectId;

    private string $topicId;

    private string $teacherToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subjectId = $this->makeSubject('Química');
        $this->topicId = $this->makeTopic($this->subjectId, 'Tabela Periódica');
        $teacher = $this->makeUser('teacher', 'Bruno');
        $this->makeClassroom($this->subjectId, teachers: [$teacher]);
        $this->teacherToken = $this->tokenFor($teacher);
    }

    public function test_the_teacher_creates_a_multiple_choice_question(): void
    {
        $id = (string) Str::uuid();

        $this->asTeacher()->postJson($this->bankUrl(), $this->sodium($id))
            ->assertStatus(201)
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.topic_id', $this->topicId)
            ->assertJsonPath('data.type', 'multiple_choice')
            ->assertJsonPath('data.statement', 'Qual é o símbolo do sódio?')
            ->assertJsonPath('data.explanation', 'Vem do latim natrium.')
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.options.0.text', 'Na')
            ->assertJsonPath('data.options.0.correct', true)
            ->assertJsonPath('data.options.0.position', 0)
            ->assertJsonPath('data.options.2.text', 'So')
            ->assertJsonCount(3, 'data.options');
    }

    public function test_resending_the_same_create_returns_the_original_and_stores_one_question(): void
    {
        $body = $this->sodium((string) Str::uuid());

        $first = $this->asTeacher()->postJson($this->bankUrl(), $body)->assertStatus(201);
        $this->asTeacher()->postJson($this->bankUrl(), $body)
            ->assertStatus(200)
            ->assertJsonPath('data.options.0.id', $first->json('data.options.0.id'));

        self::assertSame(1, DB::table('questions')->count());
        self::assertSame(3, DB::table('question_options')->count());
    }

    public function test_the_same_id_with_another_statement_is_an_idempotency_conflict(): void
    {
        $id = (string) Str::uuid();
        $this->asTeacher()->postJson($this->bankUrl(), $this->sodium($id))->assertStatus(201);

        $this->asTeacher()->postJson($this->bankUrl(), ['statement' => 'Outra?'] + $this->sodium($id))
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'system.idempotency_conflict');
    }

    public function test_a_true_false_question_gets_verdadeiro_and_falso(): void
    {
        $this->asTeacher()->postJson($this->bankUrl(), [
            'id' => (string) Str::uuid(), 'type' => 'true_false', 'statement' => 'Elétrons ficam no núcleo.', 'correct' => false,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.explanation', null)
            ->assertJsonPath('data.options.0.text', 'Verdadeiro')
            ->assertJsonPath('data.options.0.correct', false)
            ->assertJsonPath('data.options.1.text', 'Falso')
            ->assertJsonPath('data.options.1.correct', true);
    }

    public function test_a_true_false_question_with_options_is_a_validation_error(): void
    {
        $this->asTeacher()->postJson($this->bankUrl(), [
            'id' => (string) Str::uuid(), 'type' => 'true_false', 'statement' => 'x', 'correct' => true,
            'options' => [['text' => 'A', 'correct' => true], ['text' => 'B', 'correct' => false]],
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.options.0.code', 'validation.prohibited_if');
    }

    public function test_two_correct_options_are_invalid_options(): void
    {
        $body = $this->sodium((string) Str::uuid());
        $body['options'][1]['correct'] = true;

        $this->asTeacher()->postJson($this->bankUrl(), $body)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'content.question.invalid_options')
            ->assertJsonPath('error.params.reason', 'correct');
    }

    public function test_an_edit_keeps_option_ids_drops_the_omitted_and_bumps_the_version(): void
    {
        $created = $this->asTeacher()->postJson($this->bankUrl(), $this->sodium((string) Str::uuid()))->assertStatus(201);
        $id = $created->json('data.id');
        self::assertIsString($id);
        $naId = $created->json('data.options.0.id');

        $this->asTeacher()->putJson('/api/v1/questions/'.$id, [
            'version' => 1,
            'statement' => 'Símbolo do sódio?',
            'explanation' => null,
            'options' => [['id' => $naId, 'text' => 'Na', 'correct' => true], ['text' => 'Sd', 'correct' => false]],
        ])
            ->assertOk()
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.statement', 'Símbolo do sódio?')
            ->assertJsonPath('data.explanation', null)
            ->assertJsonPath('data.options.0.id', $naId)
            ->assertJsonPath('data.options.1.text', 'Sd')
            ->assertJsonCount(2, 'data.options');
    }

    public function test_an_edit_from_a_stale_version_is_refused_and_changes_nothing(): void
    {
        $created = $this->asTeacher()->postJson($this->bankUrl(), $this->sodium((string) Str::uuid()))->assertStatus(201);
        $id = $created->json('data.id');
        self::assertIsString($id);
        $options = $created->json('data.options');
        $edit = fn (string $statement): array => ['version' => 1, 'statement' => $statement, 'options' => $options];

        $this->asTeacher()->putJson('/api/v1/questions/'.$id, $edit('Primeira'))->assertOk();
        $this->asTeacher()->putJson('/api/v1/questions/'.$id, $edit('Segunda'))
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'content.question.edited_elsewhere');

        self::assertSame('Primeira', DB::table('questions')->where('id', $id)->value('statement'));
    }

    public function test_editing_a_true_false_question_with_options_is_the_wrong_type(): void
    {
        $id = $this->makeQuestion($this->topicId);

        $this->asTeacher()->putJson('/api/v1/questions/'.$id, [
            'version' => 1, 'statement' => 'x', 'options' => [['text' => 'A', 'correct' => true], ['text' => 'B', 'correct' => false]],
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.params.reason', 'type');
    }

    public function test_the_bank_lists_every_question_with_its_answer_for_authors_only(): void
    {
        $active = $this->makeQuestion($this->topicId);
        $hidden = $this->makeQuestion($this->topicId);
        DB::table('questions')->where('id', $hidden)->update(['deactivated_at' => now()]);

        $response = $this->asTeacher()->getJson($this->bankUrl())->assertOk()->assertJsonCount(2, 'data');
        // Both rows share a created_at second, so look them up by id instead of by index.
        $rows = collect((array) $response->json('data'))->keyBy('id');

        self::assertTrue(data_get($rows, $active.'.active'));
        self::assertFalse(data_get($rows, $hidden.'.active'));
        self::assertTrue(data_get($rows, $active.'.options.0.correct'));
        $this->app['auth']->forgetGuards();

        $student = $this->makeUser('student');
        $this->makeClassroom($this->subjectId, students: [$student], name: 'Química 2');

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($student))
            ->getJson($this->bankUrl())
            ->assertStatus(403);
    }

    public function test_a_question_is_deactivated_and_reactivated_without_a_new_version(): void
    {
        $id = $this->makeQuestion($this->topicId);

        $this->asTeacher()->postJson('/api/v1/questions/'.$id.'/deactivate')
            ->assertOk()
            ->assertJsonPath('data.active', false)
            ->assertJsonPath('data.version', 1);
        $this->asTeacher()->postJson('/api/v1/questions/'.$id.'/reactivate')
            ->assertOk()
            ->assertJsonPath('data.active', true);
    }

    public function test_unknown_topic_and_question_are_not_found(): void
    {
        $this->asTeacher()->getJson('/api/v1/topics/'.Str::uuid().'/questions')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'content.topic_not_found');
        $this->asTeacher()->postJson('/api/v1/questions/'.Str::uuid().'/deactivate')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'content.question_not_found');
    }

    public function test_writing_in_a_deactivated_subject_is_refused_but_reading_is_not(): void
    {
        DB::table('subjects')->where('id', $this->subjectId)->update(['deactivated_at' => now()]);

        $this->asTeacher()->getJson($this->bankUrl())->assertOk();
        $this->asTeacher()->postJson($this->bankUrl(), $this->sodium((string) Str::uuid()))
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'content.subject.inactive');
    }

    public function test_a_deactivated_topic_still_accepts_questions(): void
    {
        DB::table('topics')->where('id', $this->topicId)->update(['deactivated_at' => now()]);

        $this->asTeacher()->postJson($this->bankUrl(), $this->sodium((string) Str::uuid()))->assertStatus(201);
    }

    /** @return array{id: string, type: string, statement: string, explanation: string, options: list<array{text: string, correct: bool}>} */
    private function sodium(string $id): array
    {
        return [
            'id' => $id,
            'type' => 'multiple_choice',
            'statement' => 'Qual é o símbolo do sódio?',
            'explanation' => 'Vem do latim natrium.',
            'options' => [
                ['text' => 'Na', 'correct' => true],
                ['text' => 'S', 'correct' => false],
                ['text' => 'So', 'correct' => false],
            ],
        ];
    }

    private function asTeacher(): self
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->teacherToken);
    }

    private function bankUrl(): string
    {
        return '/api/v1/topics/'.$this->topicId.'/questions';
    }
}
