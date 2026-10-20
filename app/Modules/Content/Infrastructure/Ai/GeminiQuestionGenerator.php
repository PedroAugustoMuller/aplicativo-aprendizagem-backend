<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Ai;

use App\Modules\Content\Application\Command\SuggestQuestions\QuestionGenerator;
use App\Modules\Content\Application\Command\SuggestQuestions\SuggestionPrompt;
use App\Modules\Content\Application\Command\SuggestQuestions\SuggestionRequest;
use App\Modules\Content\Domain\Exception\SuggestionsUnavailableException;
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
                    'contents' => [['role' => 'user', 'parts' => $this->parts($request)]],
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

        return SuggestionPayload::questions($items);
    }

    /** @return list<array<string, mixed>> the material (if any) as a titled plain-text part, then the instructions */
    private function parts(SuggestionRequest $request): array
    {
        $material = $this->prompt->material($request);
        $instructions = ['text' => $this->prompt->user($request)];

        if ($material === null) {
            return [$instructions];
        }

        // Gemini parts carry no title: a label part names the document the prompt refers to.
        return [
            ['text' => 'Documento anexado: "'.SuggestionPrompt::MATERIAL_TITLE.'"'],
            ['inlineData' => ['mimeType' => 'text/plain', 'data' => base64_encode($material)]],
            $instructions,
        ];
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
