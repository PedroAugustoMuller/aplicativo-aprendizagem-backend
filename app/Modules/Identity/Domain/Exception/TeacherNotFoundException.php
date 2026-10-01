<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Exception;

use App\Modules\Identity\Domain\Error\IdentityErrorCode;
use App\Shared\Domain\Exception\NotFoundException;

final class TeacherNotFoundException extends NotFoundException
{
    public function errorCode(): string
    {
        return IdentityErrorCode::TeacherNotFound->value;
    }

    public function params(): array
    {
        return [];
    }
}
