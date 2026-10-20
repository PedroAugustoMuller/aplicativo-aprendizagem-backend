<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Http\Resource;

use App\Modules\Identity\Application\DTO\AuthenticatedUser;
use App\Shared\Infrastructure\AiSuggestions;

final class AuthenticatedUserResource
{
    /** @return array{data: array{id: string, name: string, login: string, role: string, must_change_password: bool, features: array{ai_suggestions: bool}, token: string}} */
    public static function make(AuthenticatedUser $user): array
    {
        return [
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'login' => $user->login,
                'role' => $user->role,
                'must_change_password' => $user->mustChangePassword,
                'features' => ['ai_suggestions' => AiSuggestions::enabledFor($user->role)],
                'token' => $user->token,
            ],
        ];
    }
}
