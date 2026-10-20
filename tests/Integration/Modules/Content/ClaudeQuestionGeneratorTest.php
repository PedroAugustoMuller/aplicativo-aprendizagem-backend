<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Content;

use App\Modules\Content\Application\Command\SuggestQuestions\QuestionGenerator;
use App\Modules\Content\Application\Command\SuggestQuestions\SuggestionDifficulty;
use App\Modules\Content\Application\Command\SuggestQuestions\SuggestionRequest;
use App\Modules\Content\Domain\Exception\SuggestionsUnavailableException;
use App\Modules\Content\Domain\ValueObject\QuestionType;
use App\Modules\Content\Infrastructure\Ai\ClaudeQuestionGenerator;
use App\Shared\Infrastructure\AiSuggestions;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ClaudeQuestionGeneratorTest extends TestCase
{
    private const URL = 'https://api.anthropic.com/v1/messages';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.claude.key' => 'CHAVE-SECRETA', 'services.claude.model' => 'claude-test']);
    }

    public function test_it_forces_the_question_tool_and_sends_the_key_in_a_header(): void
    {
        Http::fake([self::URL => Http::response($this->answer([]))]);

        $this->generator()->generate($this->request());

        Http::assertSent(function (Request $request): bool {
            $body = $request->data();

            return $request->url() === self::URL
                && $request->hasHeader('x-api-key', 'CHAVE-SECRETA')
                && $request->hasHeader('anthropic-version', '2023-06-01')
                && ! str_contains($request->url(), 'CHAVE')
                && data_get($body, 'model') === 'claude-test'
                && is_int(data_get($body, 'max_tokens'))
                && data_get($body, 'tool_choice.type') === 'tool'
                && data_get($body, 'tool_choice.name') === 'submit_questions'
                && data_get($body, 'tools.0.name') === 'submit_questions'
                && data_get($body, 'tools.0.input_schema.properties.questions.type') === 'array'
                && str_contains(self::text($body, 'system'), 'Brazilian Portuguese')
                && str_contains(self::text($body, 'messages.0.content'), "<source>\nO sódio reage com a água.\n</source>");
        });
    }

    public function test_it_maps_both_question_types_and_skips_junk(): void
    {
        Http::fake([self::URL => Http::response($this->answer([
            ['type' => 'multiple_choice', 'statement' => 'Símbolo?', 'explanation' => 'Natrium.', 'options' => [
                ['text' => 'Na', 'correct' => true], ['text' => 'S', 'correct' => false],
            ]],
            ['type' => 'true_false', 'statement' => 'É metal.', 'explanation' => 'Sim.', 'answer' => true],
            ['type' => 'essay', 'statement' => 'Ignorada'],
            'lixo',
        ]))]);

        $suggestions = $this->generator()->generate($this->request());

        self::assertCount(2, $suggestions);
        self::assertSame(QuestionType::MultipleChoice, $suggestions[0]->type);
        self::assertSame('Na', $suggestions[0]->options[0]->text);
        self::assertTrue($suggestions[0]->options[0]->correct);
        self::assertSame(QuestionType::TrueFalse, $suggestions[1]->type);
        self::assertTrue($suggestions[1]->answer);
    }

    /** @return iterable<string, array{0: callable(): mixed}> */
    public static function failures(): iterable
    {
        yield 'rate limited' => [static fn () => Http::response(['type' => 'error', 'error' => ['type' => 'rate_limit_error']], 429)];
        yield 'overloaded' => [static fn () => Http::response(['type' => 'error', 'error' => ['type' => 'overloaded_error']], 529)];
        yield 'server error' => [static fn () => Http::response('boom', 500)];
        yield 'bad key' => [static fn () => Http::response(['type' => 'error', 'error' => ['type' => 'authentication_error']], 401)];
        yield 'text instead of the tool' => [static fn () => Http::response([
            'stop_reason' => 'end_turn', 'content' => [['type' => 'text', 'text' => 'Claro! Aqui estão...']],
        ])];
        yield 'cut by max_tokens' => [static fn () => Http::response([
            'stop_reason' => 'max_tokens',
            'content' => [['type' => 'tool_use', 'name' => 'submit_questions', 'input' => ['questions' => [['type' => 'true_false', 'statement' => 'Corta', 'answer' => true]]]]],
        ])];
        yield 'tool input without questions' => [static fn () => Http::response([
            'stop_reason' => 'tool_use', 'content' => [['type' => 'tool_use', 'name' => 'submit_questions', 'input' => ['items' => []]]],
        ])];
        yield 'timeout' => [static fn () => throw new ConnectionException('cURL error 28: timed out')];
    }

    #[DataProvider('failures')]
    public function test_every_failure_is_unavailable_and_logged_without_secrets(callable $response): void
    {
        $spy = Log::spy();
        Http::fake([self::URL => $response]);

        try {
            $this->generator()->generate($this->request());
            self::fail('Expected SuggestionsUnavailableException');
        } catch (SuggestionsUnavailableException) {
            // expected
        }

        $spy->shouldHaveReceived('warning')->withArgs(static function (string $message, array $context): bool {
            $encoded = (string) json_encode($context);

            return $message === 'AI provider failed'
                && $context['provider'] === 'claude'
                && ! str_contains($encoded, 'CHAVE-SECRETA')
                && ! str_contains($encoded, 'sódio');
        })->once();
    }

    public function test_ai_provider_claude_selects_this_generator_and_turns_the_feature_on(): void
    {
        config(['services.ai.provider' => 'claude']);

        self::assertInstanceOf(ClaudeQuestionGenerator::class, $this->app->make(QuestionGenerator::class));
        self::assertTrue(AiSuggestions::enabledFor('teacher'));
    }

    private function generator(): ClaudeQuestionGenerator
    {
        return $this->app->make(ClaudeQuestionGenerator::class);
    }

    private function request(): SuggestionRequest
    {
        return new SuggestionRequest(
            'Química', 'Tabela Periódica', '', 2, [QuestionType::MultipleChoice, QuestionType::TrueFalse],
            SuggestionDifficulty::Medium, null, 'O sódio reage com a água.',
        );
    }

    /**
     * @param  list<mixed>  $questions
     * @return array<string, mixed>
     */
    private function answer(array $questions): array
    {
        return [
            'stop_reason' => 'tool_use',
            'content' => [['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'submit_questions', 'input' => ['questions' => $questions]]],
        ];
    }

    /** @param array<array-key, mixed> $body */
    private static function text(array $body, string $path): string
    {
        $value = data_get($body, $path);

        return is_string($value) ? $value : '';
    }
}
