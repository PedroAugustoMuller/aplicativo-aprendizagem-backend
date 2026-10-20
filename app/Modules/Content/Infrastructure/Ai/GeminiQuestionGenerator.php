<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Ai;

use App\Modules\Content\Application\Command\SuggestQuestions\QuestionGenerator;
use App\Modules\Content\Application\Command\SuggestQuestions\QuestionSuggestion;
use App\Modules\Content\Application\Command\SuggestQuestions\SuggestionPrompt;
use App\Modules\Content\Application\Command\SuggestQuestions\SuggestionRequest;
use App\Modules\Content\Domain\Exception\SuggestionsUnavailableException;
use App\Modules\Content\Domain\ValueObject\OptionDraft;
use App\Modules\Content\Domain\ValueObject\QuestionType;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google Gemini's generateContent with a JSON response schema. Any failure —
 * timeout, quota, 5xx, a blocked or cut answer — is one "unavailable"; the
 * teacher retries by hand, because every call spends the free quota.
 */
final readonly class GeminiQuestionGenerator implements QuestionGenerator
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    private const SCHEMA = [
        'type' => 'OBJECT',
        'properties' => [
            'questions' => [
                'type' => 'ARRAY',
                'items' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'type' => ['type' => 'STRING', 'enum' => ['multiple_choice', 'true_false']],
                        'statement' => ['type' => 'STRING'],
                        'explanation' => ['type' => 'STRING'],
                        'options' => [
                            'type' => 'ARRAY',
                            'items' => [
                                'type' => 'OBJECT',
                                'properties' => ['text' => ['type' => 'STRING'], 'correct' => ['type' => 'BOOLEAN']],
                                'required' => ['text', 'correct'],
                            ],
                        ],
                        'answer' => ['type' => 'BOOLEAN'],
                    ],
                    'required' => ['type', 'statement', 'explanation'],
                ],
            ],
        ],
        'required' => ['questions'],
    ];

    public function __construct(private SuggestionPrompt $prompt) {}

    public function generate(SuggestionRequest $request): array
    {
        $started = microtime(true);

        try {
            $response = Http::withHeaders(['x-goog-api-key' => Config::string('services.gemini.key')])
                ->acceptJson()
                ->connectTimeout(10)
                ->timeout(Config::integer('services.gemini.timeout'))
                ->post(sprintf(self::ENDPOINT, rawurlencode(Config::string('services.gemini.model'))), [
                    'systemInstruction' => ['parts' => [['text' => $this->prompt->system()]]],
                    'contents' => [['role' => 'user', 'parts' => [['text' => $this->prompt->user($request)]]]],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                        'responseSchema' => self::SCHEMA,
                        'temperature' => 0.7,
                    ],
                ]);
        } catch (ConnectionException) {
            $this->fail('connection', null, $started, $request);
        }

        if (! $response->successful()) {
            $this->fail('status', $response->status(), $started, $request);
        }

        $text = $response->json('candidates.0.content.parts.0.text');
        $decoded = is_string($text) ? json_decode($text, true) : null;
        $items = is_array($decoded) ? ($decoded['questions'] ?? null) : null;

        if (! is_array($items)) {
            $this->fail('unreadable', $response->status(), $started, $request);
        }

        $suggestions = [];

        foreach ($items as $item) {
            $suggestion = self::suggestion($item);

            if ($suggestion !== null) {
                $suggestions[] = $suggestion;
            }
        }

        return $suggestions;
    }

    private static function suggestion(mixed $item): ?QuestionSuggestion
    {
        if (! is_array($item) || ! is_string($item['type'] ?? null) || ! is_string($item['statement'] ?? null)) {
            return null;
        }

        $type = QuestionType::tryFrom($item['type']);
        $explanation = is_string($item['explanation'] ?? null) ? $item['explanation'] : null;

        if ($type === QuestionType::TrueFalse) {
            $answer = $item['answer'] ?? null;

            return new QuestionSuggestion($type, $item['statement'], $explanation, [], is_bool($answer) ? $answer : null);
        }

        if ($type === QuestionType::MultipleChoice && is_array($item['options'] ?? null)) {
            $options = [];

            foreach ($item['options'] as $option) {
                if (is_array($option) && is_string($option['text'] ?? null) && is_bool($option['correct'] ?? null)) {
                    $options[] = new OptionDraft(null, $option['text'], $option['correct']);
                }
            }

            return new QuestionSuggestion($type, $item['statement'], $explanation, $options, null);
        }

        return null;
    }

    /** Sizes and timings only: never the material, never the key. */
    private function fail(string $reason, ?int $status, float $started, SuggestionRequest $request): never
    {
        Log::warning('AI provider failed', [
            'provider' => 'gemini',
            'reason' => $reason,
            'status' => $status,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'source_length' => mb_strlen($request->sourceText ?? ''),
        ]);

        throw new SuggestionsUnavailableException($reason);
    }
}
