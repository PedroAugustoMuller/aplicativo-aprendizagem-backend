<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence;

use UnexpectedValueException;

/**
 * Eloquent's attribute accessors and getKey() are untyped. A blind cast would turn
 * an array into "Array" or throw on a non-Stringable object, so the shape is asserted
 * rather than assumed — loudly, at the boundary, instead of quietly downstream.
 */
final class EloquentAttribute
{
    public static function string(mixed $value, string $context): string
    {
        if (! is_string($value)) {
            throw new UnexpectedValueException(sprintf('Expected a string for %s.', $context));
        }

        return $value;
    }

    /** Postgres drivers hand back count(*) and similar aggregates as numeric strings. */
    public static function int(mixed $value, string $context): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        throw new UnexpectedValueException(sprintf('Expected an int for %s.', $context));
    }
}
