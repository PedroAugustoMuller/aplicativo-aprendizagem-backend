<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Holds a password hash. The algorithm is infrastructure's business, so this type
 * deliberately does not validate the hash's shape — bcrypt and argon2id look
 * nothing alike. The guard rejects an obviously-missing hash and nothing more:
 * it is NOT a safeguard against a caller passing plaintext by mistake.
 */
final class HashedPassword
{
    public function __construct(private readonly string $value)
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException('HashedPassword received an empty hash.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }
}
