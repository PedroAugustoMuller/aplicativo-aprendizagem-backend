<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

abstract class UnauthenticatedException extends DomainException
{
    final public function status(): int
    {
        return 401;
    }
}
