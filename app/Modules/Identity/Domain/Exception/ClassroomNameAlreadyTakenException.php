<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Exception;

use App\Modules\Identity\Domain\Error\IdentityErrorCode;
use App\Shared\Domain\Exception\ConflictException;

final class ClassroomNameAlreadyTakenException extends ConflictException
{
    public function __construct(private readonly string $name)
    {
        parent::__construct();
    }

    public function errorCode(): string
    {
        return IdentityErrorCode::ClassroomNameAlreadyTaken->value;
    }

    public function params(): array
    {
        return ['name' => $this->name];
    }
}
