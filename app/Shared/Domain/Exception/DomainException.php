<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

use RuntimeException;

abstract class DomainException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct($this->errorCode());
    }

    /**
     * A stable, translatable identifier. Never user-facing text.
     */
    abstract public function errorCode(): string;

    /**
     * Interpolation values for the client's translation catalog.
     *
     * @return array<string, scalar|null>
     */
    abstract public function params(): array;

    /**
     * Fixed by the five abstract subclasses. Concrete exceptions never choose.
     */
    abstract public function status(): int;
}
