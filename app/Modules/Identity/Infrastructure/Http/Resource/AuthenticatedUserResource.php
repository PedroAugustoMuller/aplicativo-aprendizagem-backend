<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Http\Resource;

use App\Modules\Identity\Application\DTO\AuthenticatedUser;

final class AuthenticatedUserResource
{
    /** @return array{data: array{id: string, name: string, login: string, role: string, must_change_password: bool, token: string}} */
    public static function make(AuthenticatedUser $user): array
    {
        return [
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'login' => $user->login,
                'role' => $user->role,
                'must_change_password' => $user->mustChangePassword,
                'token' => $user->token,
            ],
        ];
    }
}
