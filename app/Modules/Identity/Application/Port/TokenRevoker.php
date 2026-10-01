<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Port;

use App\Modules\Identity\Domain\ValueObject\UserId;

interface TokenRevoker
{
    public function revokeAll(UserId $id): void;

    public function revokeAllExcept(UserId $id, string $keepTokenId): void;
}
