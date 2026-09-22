<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class CreateStudentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'students' => ['required', 'array', 'min:1', 'max:50'],
            'students.*.id' => ['required', 'uuid', 'distinct'],
            'students.*.name' => ['required', 'string', 'max:120'],
        ];
    }
}
