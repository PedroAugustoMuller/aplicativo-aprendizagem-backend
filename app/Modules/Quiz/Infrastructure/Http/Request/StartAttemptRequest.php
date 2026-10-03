<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Infrastructure\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class StartAttemptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['id' => ['required', 'uuid']];
    }
}
