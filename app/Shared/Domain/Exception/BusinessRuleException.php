<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

abstract class BusinessRuleException extends DomainException
{
    final public function status(): int
    {
        return 422;
    }
}
