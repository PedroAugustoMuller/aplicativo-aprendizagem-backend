<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

use App\Shared\Domain\Error\SystemErrorCode;

/**
 * A client reused an id it already sent with a different payload. Replays with an
 * equivalent payload never reach this: they return the original result.
 */
final class IdempotencyConflictException extends ConflictException
{
    public function errorCode(): string
    {
        return SystemErrorCode::IdempotencyConflict->value;
    }

    public function params(): array
    {
        return [];
    }
}
