<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Error;

use App\Shared\Domain\Error\ErrorCode;

enum IdentityErrorCode: string implements ErrorCode
{
    case InvalidCredentials = 'identity.invalid_credentials';

    public function code(): string
    {
        return $this->value;
    }
}
