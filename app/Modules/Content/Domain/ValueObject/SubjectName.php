<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\ValueObject;

use InvalidArgumentException;
use Stringable;

final class SubjectName implements Stringable
{
    public const MAX_LENGTH = 80;

    private readonly string $value;

    public function __construct(string $value)
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw new InvalidArgumentException('SubjectName cannot be empty.');
        }

        if (mb_strlen($trimmed) > self::MAX_LENGTH) {
            throw new InvalidArgumentException(
                sprintf('SubjectName cannot exceed %d characters.', self::MAX_LENGTH),
            );
        }

        $this->value = $trimmed;
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
