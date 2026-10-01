<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class RenameSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
        ];
    }
}
