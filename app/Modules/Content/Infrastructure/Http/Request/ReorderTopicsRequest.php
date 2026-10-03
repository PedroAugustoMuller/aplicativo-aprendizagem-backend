<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class ReorderTopicsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            // present, not required: a subject with no topics has an empty order.
            'ids' => ['present', 'array'],
            'ids.*' => ['required', 'uuid'],
        ];
    }

    /** @return list<string> */
    public function ids(): array
    {
        $ids = [];

        foreach ((array) $this->input('ids', []) as $id) {
            if (is_string($id)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
