<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Exception;

use App\Modules\Identity\Domain\Error\IdentityErrorCode;
use App\Shared\Domain\Exception\ConflictException;

final class EmailAlreadyTakenException extends ConflictException
{
    public function __construct(private readonly string $email)
    {
        parent::__construct();
    }

    public function errorCode(): string
    {
        return IdentityErrorCode::EmailAlreadyTaken->value;
    }

    public function params(): array
    {
        return ['email' => $this->email];
    }
}
