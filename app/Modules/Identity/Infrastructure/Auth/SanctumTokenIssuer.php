<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Auth;

use App\Modules\Identity\Application\Port\TokenIssuer;
use App\Modules\Identity\Domain\ValueObject\UserId;
use App\Modules\Identity\Infrastructure\Persistence\UserModel;
use RuntimeException;

final class SanctumTokenIssuer implements TokenIssuer
{
    private const TOKEN_LIFETIME_DAYS = 7;

    public function issue(UserId $userId): string
    {
        $model = UserModel::query()->find($userId->value());

        if (! $model instanceof UserModel) {
            throw new RuntimeException('Cannot issue a token for a user that no longer exists.');
        }

        return $model->createToken(
            name: 'api',
            abilities: ['*'],
            expiresAt: now()->addDays(self::TOKEN_LIFETIME_DAYS),
        )->plainTextToken;
    }
}
