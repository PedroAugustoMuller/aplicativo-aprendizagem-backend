<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Exception;

use App\Modules\Identity\Domain\Error\IdentityErrorCode;
use App\Shared\Domain\Exception\NotFoundException;

final class ClassroomNotFoundException extends NotFoundException
{
    public function errorCode(): string
    {
        return IdentityErrorCode::ClassroomNotFound->value;
    }

    public function params(): array
    {
        return [];
    }
}
