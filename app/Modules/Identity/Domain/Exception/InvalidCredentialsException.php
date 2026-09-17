<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Exception;

use App\Modules\Identity\Domain\Error\IdentityErrorCode;
use App\Shared\Domain\Exception\UnauthenticatedException;

final class InvalidCredentialsException extends UnauthenticatedException
{
    public function errorCode(): string
    {
        return IdentityErrorCode::InvalidCredentials->value;
    }

    /**
     * Deliberately empty: naming which field was wrong enables account enumeration.
     */
    public function params(): array
    {
        return [];
    }
}
