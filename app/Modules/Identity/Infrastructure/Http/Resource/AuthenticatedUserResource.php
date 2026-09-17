<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Http\Resource;

use App\Modules\Identity\Application\DTO\AuthenticatedUser;

final class AuthenticatedUserResource
{
    /** @return array{data: array{id: string, name: string, email: string, token: string}} */
    public static function make(AuthenticatedUser $user): array
    {
        return [
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'token' => $user->token,
            ],
        ];
    }
}
