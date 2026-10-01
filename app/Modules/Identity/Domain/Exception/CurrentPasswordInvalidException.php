<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Exception;

use App\Modules\Identity\Domain\Error\IdentityErrorCode;
use App\Shared\Domain\Exception\BusinessRuleException;

final class CurrentPasswordInvalidException extends BusinessRuleException
{
    public function errorCode(): string
    {
        return IdentityErrorCode::CurrentPasswordInvalid->value;
    }

    public function params(): array
    {
        return [];
    }
}
