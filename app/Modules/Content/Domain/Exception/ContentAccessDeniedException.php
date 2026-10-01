<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\Exception;

use App\Shared\Domain\Error\SystemErrorCode;
use App\Shared\Domain\Exception\ForbiddenException;

/**
 * The one exception every policy denial throws in this module. Identity has its own
 * twin (AccessDeniedException) — a module never imports another module's internals.
 */
final class ContentAccessDeniedException extends ForbiddenException
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
