<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class CreateQuestionRequest extends FormRequest
{
    use ReadsQuestionInput;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        // The left-hand keys win: options/correct get their type-dependent rules.
        return [
            'id' => ['required', 'uuid'],
            'type' => ['required', 'in:multiple_choice,true_false'],
            'options' => ['required_if:type,multiple_choice', 'prohibited_if:type,true_false', 'array'],
            'correct' => ['required_if:type,true_false', 'prohibited_if:type,multiple_choice', 'boolean'],
        ] + $this->contentRules();
    }
}
