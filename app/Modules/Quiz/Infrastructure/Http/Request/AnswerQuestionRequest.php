<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Infrastructure\Http\Request;

use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;

final class AnswerQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'answer_id' => ['required', 'uuid'],
            'question_id' => ['required', 'uuid'],
            'option_id' => ['required', 'uuid'],
            'answered_at' => ['required', 'date'],
        ];
    }

    public function answeredAt(): DateTimeImmutable
    {
        return new DateTimeImmutable((string) $this->string('answered_at'));
    }
}
