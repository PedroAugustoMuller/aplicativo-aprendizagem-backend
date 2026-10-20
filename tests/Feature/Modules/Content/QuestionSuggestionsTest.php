<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Content;

use App\Modules\Content\Application\Command\SuggestQuestions\QuestionGenerator;
use App\Modules\Content\Application\Command\SuggestQuestions\QuestionSuggestion;
use App\Modules\Content\Application\Command\SuggestQuestions\SuggestionRequest;
use App\Modules\Content\Domain\Exception\SuggestionsUnavailableException;
use App\Modules\Content\Domain\ValueObject\OptionDraft;
use App\Modules\Content\Domain\ValueObject\QuestionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\Feature\Support\ActsAsUsers;
use Tests\Feature\Support\RecordingQuestionGenerator;
use Tests\TestCase;

final class QuestionSuggestionsTest extends TestCase
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

    public function test_the_teacher_gets_valid_suggestions_and_nothing_is_stored(): void
    {
        $generator = $this->stub([
            new QuestionSuggestion(QuestionType::MultipleChoice, 'Símbolo do sódio?', 'Natrium.', [
                new OptionDraft(null, 'Na', true), new OptionDraft(null, 'S', false),
            ], null),
            new QuestionSuggestion(QuestionType::TrueFalse, 'O sódio é um metal.', null, [], true),
            new QuestionSuggestion(QuestionType::MultipleChoice, 'Sem certa?', null, [
                new OptionDraft(null, 'A', false), new OptionDraft(null, 'B', false),
            ], null),
        ]);

        $this->asTeacher()->postJson($this->url(), $this->body(['count' => 3]))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.type', 'multiple_choice')
            ->assertJsonPath('data.0.statement', 'Símbolo do sódio?')
            ->assertJsonPath('data.0.explanation', 'Natrium.')
            ->assertJsonPath('data.0.options.0.text', 'Na')
            ->assertJsonPath('data.0.options.0.correct', true)
            ->assertJsonMissingPath('data.0.correct')
            ->assertJsonPath('data.1.type', 'true_false')
            ->assertJsonPath('data.1.correct', true)
            ->assertJsonMissingPath('data.1.options');

        self::assertSame(0, DB::table('questions')->count());
        self::assertNotNull($generator->last);
        self::assertSame('Química', $generator->last->subjectName);
        self::assertSame('Tabela Periódica', $generator->last->topicName);
        self::assertSame(3, $generator->last->count);
    }

    public function test_the_request_reaches_the_generator_as_sent(): void
    {
        $generator = $this->stub([new QuestionSuggestion(QuestionType::TrueFalse, 'Ok.', null, [], true)]);

        $this->asTeacher()->postJson($this->url(), [
            'count' => 2, 'types' => ['true_false'], 'difficulty' => 'hard',
            'notes' => 'Sem cálculos', 'source_text' => 'O sódio reage com a água.',
        ])->assertOk();

        self::assertNotNull($generator->last);
        self::assertSame([QuestionType::TrueFalse], $generator->last->types);
        self::assertSame('hard', $generator->last->difficulty->value);
        self::assertSame('Sem cálculos', $generator->last->notes);
        self::assertSame('O sódio reage com a água.', $generator->last->sourceText);
    }

    public function test_no_valid_suggestion_is_the_empty_code(): void
    {
        $this->stub([new QuestionSuggestion(QuestionType::TrueFalse, 'Sem resposta.', null, [], null)]);

        $this->asTeacher()->postJson($this->url(), $this->body())
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'content.suggestions.empty');
    }

    public function test_a_provider_failure_is_the_unavailable_code(): void
    {
        $this->app->instance(QuestionGenerator::class, new class implements QuestionGenerator
        {
            public function generate(SuggestionRequest $request): array
            {
                throw new SuggestionsUnavailableException('timeout');
            }
        });

        $this->asTeacher()->postJson($this->url(), $this->body())
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'content.suggestions.unavailable');
    }

    public function test_without_a_provider_the_route_is_disabled(): void
    {
        config(['services.ai.provider' => '']);

        $this->asTeacher()->postJson($this->url(), $this->body())
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'content.suggestions.disabled');
    }

    public function test_the_fake_provider_answers_without_network(): void
    {
        config(['services.ai.provider' => 'fake']);

        $this->asTeacher()->postJson($this->url(), $this->body(['count' => 3]))
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_every_field_is_validated(): void
    {
        $this->stub([]);

        $response = $this->asTeacher()->postJson($this->url(), [
            'count' => 11, 'types' => ['essay'], 'difficulty' => 'extreme',
            'notes' => str_repeat('a', 501), 'source_text' => str_repeat('a', 30001),
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation.failed')
            ->assertJsonPath('errors.count.0.code', 'validation.max')
            ->assertJsonPath('errors.difficulty.0.code', 'validation.in')
            ->assertJsonPath('errors.notes.0.code', 'validation.max')
            ->assertJsonPath('errors.source_text.0.code', 'validation.max');

        // The key is literally "types.0"; dot notation cannot reach it.
        self::assertSame('validation.in', data_get($response->json('errors'), ['types.0', 0, 'code']));

        $this->asTeacher()->postJson($this->url(), ['count' => 0, 'types' => [], 'difficulty' => 'easy'])
            ->assertStatus(422)
            ->assertJsonPath('errors.count.0.code', 'validation.min')
            ->assertJsonPath('errors.types.0.code', 'validation.required');
    }

    public function test_thirty_thousand_characters_of_source_text_are_accepted(): void
    {
        $this->stub([new QuestionSuggestion(QuestionType::TrueFalse, 'Ok.', null, [], true)]);

        $this->asTeacher()->postJson($this->url(), $this->body(['source_text' => str_repeat('á', 30000)]))->assertOk();
    }

    public function test_an_inactive_subject_is_refused(): void
    {
        $this->stub([]);
        DB::table('subjects')->where('id', $this->subjectId)->update(['deactivated_at' => now()]);

        $this->asTeacher()->postJson($this->url(), $this->body())
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'content.subject.inactive');
    }

    public function test_a_teacher_of_another_subject_and_a_student_are_refused(): void
    {
        $this->stub([]);
        $other = $this->makeUser('teacher', 'Outro');
        $this->makeClassroom($this->makeSubject('Biologia'), teachers: [$other], name: 'Bio 1');
        $student = $this->makeUser('student', 'Aluno');

        $this->withToken($this->tokenFor($other))->postJson($this->url(), $this->body())->assertStatus(403);
        $this->withToken($this->tokenFor($student))->postJson($this->url(), $this->body())->assertStatus(403);
    }

    public function test_an_unknown_topic_is_not_found(): void
    {
        $this->stub([]);

        $this->asTeacher()->postJson('/api/v1/topics/0190a2b4-0000-7000-8000-00000000dead/question-suggestions', $this->body())
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'content.topic_not_found');
    }

    public function test_each_user_is_limited_per_hour(): void
    {
        config(['services.ai.limits.per_user_hour' => 2]);
        $this->stub([new QuestionSuggestion(QuestionType::TrueFalse, 'Ok.', null, [], true)]);

        $this->asTeacher()->postJson($this->url(), $this->body())->assertOk();
        $this->asTeacher()->postJson($this->url(), $this->body())->assertOk();
        $response = $this->asTeacher()->postJson($this->url(), $this->body())
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'http.too_many_requests');

        self::assertGreaterThan(0, $response->json('error.params.retry_after'));
    }

    public function test_the_whole_system_is_limited_per_day(): void
    {
        config(['services.ai.limits.global_day' => 1]);
        $this->stub([new QuestionSuggestion(QuestionType::TrueFalse, 'Ok.', null, [], true)]);
        $admin = $this->makeUser('admin', 'Admin');

        $this->asTeacher()->postJson($this->url(), $this->body())->assertOk();
        $this->withToken($this->tokenFor($admin))->postJson($this->url(), $this->body())->assertStatus(429);
    }

    public function test_failed_generations_do_not_spend_the_teachers_own_limit(): void
    {
        config(['services.ai.limits.per_user_hour' => 1]);
        $this->app->instance(QuestionGenerator::class, new class implements QuestionGenerator
        {
            public function generate(SuggestionRequest $request): array
            {
                throw new SuggestionsUnavailableException('quota');
            }
        });

        $this->asTeacher()->postJson($this->url(), $this->body())->assertStatus(503);
        $this->asTeacher()->postJson($this->url(), $this->body())->assertStatus(503);
        $this->asTeacher()->postJson($this->url(), ['count' => 99])->assertStatus(422);

        $this->stub([new QuestionSuggestion(QuestionType::TrueFalse, 'Ok.', null, [], true)]);
        $this->asTeacher()->postJson($this->url(), $this->body())->assertOk();
        $this->asTeacher()->postJson($this->url(), $this->body())->assertStatus(429);
    }

    public function test_provider_failures_still_count_for_the_whole_school(): void
    {
        config(['services.ai.limits.global_day' => 1]);
        $this->app->instance(QuestionGenerator::class, new class implements QuestionGenerator
        {
            public function generate(SuggestionRequest $request): array
            {
                throw new SuggestionsUnavailableException('quota');
            }
        });

        $this->asTeacher()->postJson($this->url(), ['count' => 99])->assertStatus(422);
        $this->asTeacher()->postJson($this->url(), $this->body())->assertStatus(503);
        $this->asTeacher()->postJson($this->url(), $this->body())->assertStatus(429);
    }

    public function test_a_success_is_logged_without_the_source_text(): void
    {
        $spy = Log::spy();
        $this->stub([
            new QuestionSuggestion(QuestionType::TrueFalse, 'Ok.', null, [], true),
            new QuestionSuggestion(QuestionType::TrueFalse, 'Sem resposta.', null, [], null),
        ]);

        $this->asTeacher()->postJson($this->url(), $this->body(['count' => 3, 'source_text' => 'SEGREDO-DA-APOSTILA']))->assertOk();

        $spy->shouldHaveReceived('info')->withArgs(static function (string $message, array $context): bool {
            return $message === 'AI suggestions generated'
                && $context['requested'] === 3
                && $context['received'] === 2
                && $context['kept'] === 1
                && ! str_contains((string) json_encode($context), 'SEGREDO');
        })->once();
    }

    /** @param list<QuestionSuggestion> $suggestions */
    private function stub(array $suggestions): RecordingQuestionGenerator
    {
        $generator = new RecordingQuestionGenerator($suggestions);
        $this->app->instance(QuestionGenerator::class, $generator);

        return $generator;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function body(array $overrides = []): array
    {
        return $overrides + ['count' => 2, 'types' => ['multiple_choice', 'true_false'], 'difficulty' => 'medium'];
    }

    private function url(): string
    {
        return "/api/v1/topics/{$this->topicId}/question-suggestions";
    }

    private function asTeacher(): self
    {
        return $this->withToken($this->teacherToken);
    }
}
