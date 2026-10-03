<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class CreateTopicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'id' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:120'],
            // ConvertEmptyStringsToNull turns "" into null: an empty description is allowed.
            'description' => ['present', 'nullable', 'string', 'max:500'],
        ];
    }
}
