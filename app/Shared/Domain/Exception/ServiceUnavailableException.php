<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

/** An outside service this request depends on is off or failing; trying again later may work. */
abstract class ServiceUnavailableException extends DomainException
{
    final public function status(): int
    {
        return 503;
    }
}
