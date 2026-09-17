<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Port;

use App\Modules\Identity\Domain\ValueObject\UserId;

interface TokenIssuer
{
    public function issue(UserId $userId): string;
}
