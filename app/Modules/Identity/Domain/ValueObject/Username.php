<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\ValueObject;

use InvalidArgumentException;
use Stringable;

final class Username implements Stringable
{
    public const MAX_LENGTH = 60;

    private const PATTERN = '/^[a-z0-9]+(\.[a-z0-9]+)*$/';

    private readonly string $value;

    public function __construct(string $value)
    {
        $normalised = mb_strtolower(trim($value));

        if (mb_strlen($normalised) > self::MAX_LENGTH || preg_match(self::PATTERN, $normalised) !== 1) {
            throw new InvalidArgumentException('Username received a malformed value.');
        }

        $this->value = $normalised;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
