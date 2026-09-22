<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Exception;

use App\Shared\Domain\Error\SystemErrorCode;
use App\Shared\Domain\Exception\ForbiddenException;

/**
 * The one exception every policy denial throws in this module: an authenticated
 * actor whose role does not grant the action. Content gets its own twin.
 */
final class AccessDeniedException extends ForbiddenException
{
    public function errorCode(): string
    {
        return SystemErrorCode::Forbidden->value;
    }

    /** Deliberately empty: naming the missing permission maps the permission model for a prober. */
    public function params(): array
    {
        return [];
    }
}
