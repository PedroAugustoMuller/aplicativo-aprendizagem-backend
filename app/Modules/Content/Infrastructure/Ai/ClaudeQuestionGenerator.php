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
 * Anthropic's Messages API. The questions come back as the input of a forced
 * tool call, so they are already structured JSON. Any failure — timeout, 429,
 * 529 overload, 5xx, a bad key, no tool call, an answer cut by max_tokens — is
 * one "unavailable"; the teacher retries by hand.
 */
final readonly class ClaudeQuestionGenerator implements QuestionGenerator
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    private const API_VERSION = '2023-06-01';

    private const TOOL = 'submit_questions';

    // Ten questions with explanations fit comfortably.
    private const MAX_TOKENS = 8000;

    private const SCHEMA = [
        'type' => 'object',
        'properties' => [
            'questions' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'type' => ['type' => 'string', 'enum' => ['multiple_choice', 'true_false']],
                        'statement' => ['type' => 'string'],
                        'explanation' => ['type' => 'string'],
                        'options' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => ['text' => ['type' => 'string'], 'correct' => ['type' => 'boolean']],
                                'required' => ['text', 'correct'],
                            ],
                        ],
                        'answer' => ['type' => 'boolean'],
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
            $response = Http::withHeaders([
                'x-api-key' => Config::string('services.claude.key'),
                'anthropic-version' => self::API_VERSION,
            ])
                ->acceptJson()
                ->connectTimeout(10)
                ->timeout(Config::integer('services.claude.timeout'))
                ->post(self::ENDPOINT, [
                    'model' => Config::string('services.claude.model'),
                    'max_tokens' => self::MAX_TOKENS,
                    'system' => $this->prompt->system(),
                    'messages' => [['role' => 'user', 'content' => $this->content($request)]],
                    'tools' => [[
                        'name' => self::TOOL,
                        'description' => 'Submit the quiz questions you wrote.',
                        'input_schema' => self::SCHEMA,
                    ]],
                    'tool_choice' => ['type' => 'tool', 'name' => self::TOOL],
                ]);
        } catch (ConnectionException) {
            $this->fail('connection', null, $started, $request);
        }

        if (! $response->successful()) {
            $this->fail('status', $response->status(), $started, $request);
        }

        // Cut short, the last question may be half written: the batch is not trusted.
        if ($response->json('stop_reason') === 'max_tokens') {
            $this->fail('truncated', $response->status(), $started, $request);
        }

        $items = $this->toolQuestions($response->json('content'));

        if ($items === null) {
            $this->fail('unreadable', $response->status(), $started, $request);
        }

        return SuggestionPayload::questions($items);
    }

    /** @return array<array-key, mixed>|null */
    private function toolQuestions(mixed $content): ?array
    {
        if (! is_array($content)) {
            return null;
        }

        foreach ($content as $block) {
            if (is_array($block) && ($block['type'] ?? null) === 'tool_use' && ($block['name'] ?? null) === self::TOOL) {
                $questions = is_array($block['input'] ?? null) ? ($block['input']['questions'] ?? null) : null;

                return is_array($questions) ? $questions : null;
            }
        }

        return null;
    }

    /** @return list<array<string, mixed>> the material (if any) as its own document, then the instructions */
    private function content(SuggestionRequest $request): array
    {
        $material = $this->prompt->material($request);
        $instructions = ['type' => 'text', 'text' => $this->prompt->user($request)];

        if ($material === null) {
            return [$instructions];
        }

        return [
            [
                'type' => 'document',
                'source' => ['type' => 'text', 'media_type' => 'text/plain', 'data' => $material],
                'title' => SuggestionPrompt::MATERIAL_TITLE,
            ],
            $instructions,
        ];
    }

    /** Sizes and timings only: never the material, never the key. */
    private function fail(string $reason, ?int $status, float $started, SuggestionRequest $request): never
    {
        Log::warning('AI provider failed', [
            'provider' => 'claude',
            'reason' => $reason,
            'status' => $status,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'source_length' => mb_strlen($request->sourceText ?? ''),
        ]);

        throw new SuggestionsUnavailableException($reason);
    }
}
