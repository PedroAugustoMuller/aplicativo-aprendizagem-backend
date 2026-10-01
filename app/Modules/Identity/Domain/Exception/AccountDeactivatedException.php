<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Exception;

use App\Modules\Identity\Domain\Error\IdentityErrorCode;
use App\Shared\Domain\Exception\UnauthenticatedException;

final class AccountDeactivatedException extends UnauthenticatedException
{
    public function errorCode(): string
    {
        return IdentityErrorCode::AccountDeactivated->value;
    }

    public function params(): array
    {
        return [];
    }
}
