<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class SuggestQuestionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'count' => ['required', 'integer', 'min:1', 'max:10'],
            'types' => ['required', 'array', 'min:1'],
            'types.*' => ['string', 'distinct', 'in:multiple_choice,true_false'],
            'difficulty' => ['required', 'string', 'in:easy,medium,hard'],
            'notes' => ['nullable', 'string', 'max:500'],
            'source_text' => ['nullable', 'string', 'max:30000'],
        ];
    }

    /** @return list<string> */
    public function types(): array
    {
        $types = $this->input('types');

        return is_array($types) ? array_values(array_filter($types, is_string(...))) : [];
    }

    public function optionalString(string $key): ?string
    {
        $value = $this->input($key);

        return is_string($value) ? $value : null;
    }
}
