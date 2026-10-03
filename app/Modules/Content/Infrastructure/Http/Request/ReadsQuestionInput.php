<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Http\Request;

use App\Modules\Content\Domain\ValueObject\OptionDraft;

/**
 * Shapes and sizes only. Counting options and correct answers is the domain's
 * rule (one error code, content.question.invalid_options), not validation's.
 */
trait ReadsQuestionInput
{
    /** @return array<string, list<string>> */
    private function contentRules(): array
    {
        return [
            'statement' => ['required', 'string', 'max:1000'],
            'explanation' => ['nullable', 'string', 'max:1000'],
            'options' => ['array'],
            'options.*' => ['array'],
            'options.*.id' => ['nullable', 'uuid'],
            'options.*.text' => ['required', 'string', 'max:200'],
            'options.*.correct' => ['required', 'boolean'],
            'correct' => ['boolean'],
        ];
    }

    /** @return list<OptionDraft>|null null when the body carries no options */
    public function optionDrafts(): ?array
    {
        $raw = $this->input('options');

        if (! is_array($raw)) {
            return null;
        }

        $drafts = [];

        foreach ($raw as $option) {
            if (! is_array($option)) {
                continue;
            }

            $id = $option['id'] ?? null;
            $text = $option['text'] ?? '';

            $drafts[] = new OptionDraft(
                is_string($id) ? $id : null,
                is_string($text) ? $text : '',
                filter_var($option['correct'] ?? false, FILTER_VALIDATE_BOOLEAN),
            );
        }

        return $drafts;
    }

    /** The true/false answer, or null when the body carries none. */
    public function answer(): ?bool
    {
        return $this->input('correct') === null ? null : $this->boolean('correct');
    }

    public function explanationText(): ?string
    {
        $value = $this->input('explanation');

        return is_string($value) ? $value : null;
    }
}
