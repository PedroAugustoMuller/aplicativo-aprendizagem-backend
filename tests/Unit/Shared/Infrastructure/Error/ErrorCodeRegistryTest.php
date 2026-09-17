<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Error;

use App\Shared\Domain\Error\ErrorCode;
use App\Shared\Infrastructure\Error\ErrorCodeRegistry;
use PHPUnit\Framework\TestCase;

final class ErrorCodeRegistryTest extends TestCase
{
    public function test_it_collects_codes_from_every_registered_enum(): void
    {
        $registry = new ErrorCodeRegistry([FirstFakeErrorCode::class, SecondFakeErrorCode::class]);

        self::assertSame(
            ['alpha.one', 'alpha.two', 'beta.one'],
            $registry->all(),
        );
    }

    public function test_it_sorts_and_deduplicates(): void
    {
        $registry = new ErrorCodeRegistry([SecondFakeErrorCode::class, SecondFakeErrorCode::class]);

        self::assertSame(['beta.one'], $registry->all());
    }
}

enum FirstFakeErrorCode: string implements ErrorCode
{
    case Two = 'alpha.two';
    case One = 'alpha.one';

    public function code(): string
    {
        return $this->value;
    }
}

enum SecondFakeErrorCode: string implements ErrorCode
{
    case One = 'beta.one';

    public function code(): string
    {
        return $this->value;
    }
}
