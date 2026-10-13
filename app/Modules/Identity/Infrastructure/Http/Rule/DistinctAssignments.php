<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Http\Rule;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** No (teacher, subject) pair twice. Reported as validation.invalid on `assignments`. */
final class DistinctAssignments implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            return;
        }

        $seen = [];
        foreach ($value as $row) {
            if (! is_array($row)) {
                continue;
            }

            $teacher = is_string($row['teacher_id'] ?? null) ? $row['teacher_id'] : '';
            $subject = is_string($row['subject_id'] ?? null) ? $row['subject_id'] : '*';
            $key = $teacher.'|'.$subject;

            if (isset($seen[$key])) {
                $fail('Duplicated assignment.');

                return;
            }

            $seen[$key] = true;
        }
    }
}
