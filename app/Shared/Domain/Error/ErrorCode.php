<?php

declare(strict_types=1);

namespace App\Shared\Domain\Error;

/**
 * A stable identifier the client translates. Implemented by one backed enum per module.
 */
interface ErrorCode
{
    public function code(): string;
}
