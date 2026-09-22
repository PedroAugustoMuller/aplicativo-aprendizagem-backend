<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class CreateClassroomRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:80'],
            'subject_id' => ['required', 'uuid'],
        ];
    }
}
