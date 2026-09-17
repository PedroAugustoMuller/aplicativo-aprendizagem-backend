<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\ValueObject;

use InvalidArgumentException;
use Stringable;

final class Email implements Stringable
{
    private readonly string $value;

    public function __construct(string $value)
    {
        $normalised = mb_strtolower(trim($value));

        if (filter_var($normalised, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Email received a malformed address.');
        }

        $this->value = $normalised;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
