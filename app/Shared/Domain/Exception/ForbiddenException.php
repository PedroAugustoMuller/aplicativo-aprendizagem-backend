<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

/**
 * The caller is authenticated but not allowed. Distinct from 401 on purpose:
 * the frontend clears the session on 401 and must NOT do so on 403.
 */
abstract class ForbiddenException extends DomainException
{
    final public function status(): int
    {
        return 403;
    }
}
