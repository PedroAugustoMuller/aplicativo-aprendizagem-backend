<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Foundation\Testing\TestCase as LaravelTestCase;
use PHPUnit\Framework\TestCase;

final class CanaryTest extends TestCase
{
    public function test_unit_suite_runs_without_booting_laravel(): void
    {
        // Extending Laravel's TestCase would boot the framework and a database
        // connection for every unit test. This suite must stay framework-free.
        //
        // is_subclass_of() with a class-string built from get_class() is a
        // runtime check: PHPStan cannot fold get_class($this) to a literal, so
        // it cannot narrow this the way it narrowed a direct
        // assertNotInstanceOf(LaravelTestCase::class, $this) or an
        // is_subclass_of(self::class, ...) call (self::class IS a compile-time
        // literal and gets narrowed just the same). This keeps the assertion
        // structural — it fails the moment this class's `extends` clause
        // changes to Laravel's TestCase — and independent of suite ordering,
        // unlike checking whether some Illuminate class happens to be
        // autoloaded yet.
        self::assertFalse(is_subclass_of(get_class($this), LaravelTestCase::class));
    }
}
