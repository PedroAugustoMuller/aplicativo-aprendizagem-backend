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
}
