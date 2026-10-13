<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Http\Request;

use App\Modules\Identity\Infrastructure\Http\Rule\DistinctAssignments;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class AssignTeachersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string|ValidationRule>> */
    public function rules(): array
    {
        return [
            'assignments' => ['present', 'array', 'max:60', new DistinctAssignments],
            'assignments.*' => ['array:teacher_id,subject_id'],
            'assignments.*.teacher_id' => ['required', 'uuid'],
            'assignments.*.subject_id' => ['present', 'nullable', 'uuid'],
        ];
    }
}
