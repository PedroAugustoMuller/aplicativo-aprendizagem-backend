<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity\Domain;

use App\Modules\Identity\Domain\Exception\InvalidCredentialsException;
use PHPUnit\Framework\TestCase;

final class InvalidCredentialsExceptionTest extends TestCase
{
    public function test_it_carries_no_params_to_prevent_account_enumeration(): void
    {
        $exception = new InvalidCredentialsException;

        self::assertSame(401, $exception->status());
        self::assertSame('identity.invalid_credentials', $exception->errorCode());
        self::assertSame([], $exception->params());
    }
}
