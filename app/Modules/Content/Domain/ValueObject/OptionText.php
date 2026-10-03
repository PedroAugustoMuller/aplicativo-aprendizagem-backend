<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\ValueObject;

use App\Modules\Content\Domain\Exception\InvalidQuestionOptionsException;
use InvalidArgumentException;
use Stringable;

final class OptionText implements Stringable
{
    public const MAX_LENGTH = 200;

    private readonly string $value;

    public function __construct(string $value)
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw new InvalidQuestionOptionsException('empty');
        }

        if (mb_strlen($trimmed) > self::MAX_LENGTH) {
            throw new InvalidArgumentException(sprintf('OptionText cannot exceed %d characters.', self::MAX_LENGTH));
        }

        $this->value = $trimmed;
    }

    public function value(): string
    {
        return $this->value;
    }

    /** Two options that differ only in case or spacing are the same answer to a student. */
    public function comparisonKey(): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', ' ', $this->value));
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
