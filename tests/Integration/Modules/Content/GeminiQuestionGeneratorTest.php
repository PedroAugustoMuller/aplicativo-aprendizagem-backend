<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Content;

use App\Modules\Content\Application\Command\SuggestQuestions\SuggestionDifficulty;
use App\Modules\Content\Application\Command\SuggestQuestions\SuggestionRequest;
use App\Modules\Content\Domain\Exception\SuggestionsUnavailableException;
use App\Modules\Content\Domain\ValueObject\QuestionType;
use App\Modules\Content\Infrastructure\Ai\GeminiQuestionGenerator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class GeminiQuestionGeneratorTest extends TestCase
{
    private const URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-test:generateContent';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.gemini.key' => 'CHAVE-SECRETA', 'services.gemini.model' => 'gemini-test']);
    }

    public function test_it_sends_the_prompt_with_a_json_schema_and_the_key_in_a_header(): void
    {
        Http::fake([self::URL => Http::response($this->answer(['questions' => []]))]);

        $this->generator()->generate($this->request());

        Http::assertSent(function (Request $request): bool {
            $body = $request->data();

            return $request->url() === self::URL
                && $request->hasHeader('x-goog-api-key', 'CHAVE-SECRETA')
                && ! str_contains($request->url(), 'CHAVE')
                && data_get($body, 'generationConfig.responseMimeType') === 'application/json'
                && data_get($body, 'generationConfig.responseSchema.properties.questions.type') === 'ARRAY'
                && str_contains(self::text($body, 'systemInstruction.parts.0.text'), 'Brazilian Portuguese')
                && str_contains(self::text($body, 'contents.0.parts.0.text'), "<source>\nO sódio reage com a água.\n</source>");
        });
    }

    public function test_it_maps_both_question_types(): void
    {
        Http::fake([self::URL => Http::response($this->answer(['questions' => [
            ['type' => 'multiple_choice', 'statement' => 'Símbolo?', 'explanation' => 'Natrium.', 'options' => [
                ['text' => 'Na', 'correct' => true], ['text' => 'S', 'correct' => false],
            ]],
            ['type' => 'true_false', 'statement' => 'É metal.', 'explanation' => 'Sim.', 'answer' => true],
            ['type' => 'essay', 'statement' => 'Ignorada'],
            'lixo',
        ]]))]);

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
        yield 'quota' => [static fn () => Http::response(['error' => ['code' => 429]], 429)];
        yield 'server error' => [static fn () => Http::response('boom', 500)];
        yield 'no candidates (blocked)' => [static fn () => Http::response(['promptFeedback' => ['blockReason' => 'SAFETY']])];
        yield 'cut json' => [static fn () => Http::response(['candidates' => [['finishReason' => 'MAX_TOKENS', 'content' => ['parts' => [['text' => '{"questions": [{"type"']]]]]])];
        yield 'prose around json' => [static fn () => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'Claro! {"questions": []}']]]]]])];
        yield 'no questions key' => [static fn () => Http::response(['candidates' => [['content' => ['parts' => [['text' => '{"items": []}']]]]]])];
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
                && $context['provider'] === 'gemini'
                && ! str_contains($encoded, 'CHAVE-SECRETA')
                && ! str_contains($encoded, 'sódio');
        })->once();
    }

    /** @param array<array-key, mixed> $body */
    private static function text(array $body, string $path): string
    {
        $value = data_get($body, $path);

        return is_string($value) ? $value : '';
    }

    private function generator(): GeminiQuestionGenerator
    {
        return $this->app->make(GeminiQuestionGenerator::class);
    }

    private function request(): SuggestionRequest
    {
        return new SuggestionRequest(
            'Química', 'Tabela Periódica', '', 2, [QuestionType::MultipleChoice, QuestionType::TrueFalse],
            SuggestionDifficulty::Medium, null, 'O sódio reage com a água.',
        );
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    private function answer(array $json): array
    {
        return ['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => (string) json_encode($json)]]]]]];
    }
}
