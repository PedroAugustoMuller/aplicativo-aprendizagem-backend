<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Auth;

use App\Modules\Identity\Application\Port\TokenRevoker;
use App\Modules\Identity\Domain\ValueObject\UserId;
use App\Modules\Identity\Infrastructure\Persistence\UserModel;
use Laravel\Sanctum\PersonalAccessToken;

final class SanctumTokenRevoker implements TokenRevoker
{
    public function revokeAll(UserId $id): void
    {
        PersonalAccessToken::query()
            ->where('tokenable_type', UserModel::class)
            ->where('tokenable_id', $id->value())
            ->delete();
    }

    public function revokeAllExcept(UserId $id, string $keepTokenId): void
    {
        PersonalAccessToken::query()
            ->where('tokenable_type', UserModel::class)
            ->where('tokenable_id', $id->value())
            ->whereKeyNot($keepTokenId)
            ->delete();
    }
}
