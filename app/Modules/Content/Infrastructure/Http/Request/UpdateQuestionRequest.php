<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

/** Which of options/correct is required depends on the stored type: the handler checks it. */
final class UpdateQuestionRequest extends FormRequest
{
    use ReadsQuestionInput;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['version' => ['required', 'integer', 'min:1']] + $this->contentRules();
    }
}
