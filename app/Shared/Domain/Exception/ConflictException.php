<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

abstract class ConflictException extends DomainException
{
    final public function status(): int
    {
        return 409;
    }
}
