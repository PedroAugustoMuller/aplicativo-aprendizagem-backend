<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Http\Controller;

use App\Modules\Content\Application\Command\SuggestQuestions\QuestionSuggestion;
use App\Modules\Content\Application\Command\SuggestQuestions\SuggestQuestionsCommand;
use App\Modules\Content\Application\Command\SuggestQuestions\SuggestQuestionsHandler;
use App\Modules\Content\Domain\ValueObject\OptionDraft;
use App\Modules\Content\Domain\ValueObject\QuestionType;
use App\Modules\Content\Infrastructure\Http\Request\SuggestQuestionsRequest;
use App\Shared\Infrastructure\Http\ActorFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

final class QuestionSuggestionController
{
    public function __construct(private readonly ActorFactory $actors) {}

    public function store(SuggestQuestionsRequest $request, string $id, SuggestQuestionsHandler $handler): JsonResponse
    {
        $actor = $this->actors->fromRequest($request);
        $count = $request->integer('count');

        $result = $handler->handle(new SuggestQuestionsCommand(
            $actor,
            $id,
            $count,
            $request->types(),
            (string) $request->string('difficulty'),
            $request->optionalString('notes'),
            $request->optionalString('source_text'),
        ));

        // Counts only: the source text is the school's material and never reaches a log.
        Log::info('AI suggestions generated', [
            'user_id' => $actor->userId,
            'topic_id' => $id,
            'requested' => $count,
            'received' => $result->received,
            'kept' => count($result->kept),
            'source_length' => mb_strlen($request->optionalString('source_text') ?? ''),
        ]);

        return new JsonResponse(['data' => array_map($this->present(...), $result->kept)]);
    }

    /** @return array<string, mixed> the create-question body, without id */
    private function present(QuestionSuggestion $suggestion): array
    {
        $base = ['type' => $suggestion->type->value, 'statement' => $suggestion->statement, 'explanation' => $suggestion->explanation];

        if ($suggestion->type === QuestionType::TrueFalse) {
            return $base + ['correct' => $suggestion->answer];
        }

        return $base + ['options' => array_map(
            static fn (OptionDraft $o): array => ['text' => $o->text, 'correct' => $o->correct],
            $suggestion->options,
        )];
    }
}
