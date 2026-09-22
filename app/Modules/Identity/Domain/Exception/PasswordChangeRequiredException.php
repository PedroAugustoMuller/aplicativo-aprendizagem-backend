<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Exception;

use App\Modules\Identity\Domain\Error\IdentityErrorCode;
use App\Shared\Domain\Exception\ForbiddenException;

final class PasswordChangeRequiredException extends ForbiddenException
{
    public function errorCode(): string
    {
        return IdentityErrorCode::PasswordChangeRequired->value;
    }

    public function params(): array
    {
        return [];
    }
}
