<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class DatabaseConnectionTest extends TestCase
{
    public function test_it_connects_to_the_test_database(): void
    {
        self::assertSame('quimica_test', DB::connection()->getDatabaseName());
    }
}
