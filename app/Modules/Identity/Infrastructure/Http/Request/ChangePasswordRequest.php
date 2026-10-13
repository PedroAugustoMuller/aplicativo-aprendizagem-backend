<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Http\Request;

use App\Modules\Identity\Infrastructure\Persistence\UserModel;
use Illuminate\Foundation\Http\FormRequest;

final class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        // The handler ignores it while a change is pending; see ChangePasswordHandler.
        $user = $this->user();
        $pending = $user instanceof UserModel && $user->getAttribute('must_change_password') === true;

        return [
            'current_password' => [$pending ? 'nullable' : 'required', 'string', 'max:255'],
            'new_password' => ['required', 'string', 'min:8', 'max:255'],
        ];
    }
}
