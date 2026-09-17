<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\ValueObject;

use App\Shared\Domain\ValueObject\Uuid;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class UuidTest extends TestCase
{
    public function test_it_accepts_a_valid_uuid(): void
    {
        $id = new TestUuid('0199c0de-1a2b-7c3d-8e4f-5a6b7c8d9e0f');

        self::assertSame('0199c0de-1a2b-7c3d-8e4f-5a6b7c8d9e0f', $id->value());
    }

    public function test_it_rejects_a_malformed_uuid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TestUuid('not-a-uuid');
    }

    public function test_random_produces_the_concrete_subclass(): void
    {
        self::assertInstanceOf(TestUuid::class, TestUuid::random());
    }

    public function test_equality_compares_by_value(): void
    {
        $value = '0199c0de-1a2b-7c3d-8e4f-5a6b7c8d9e0f';

        self::assertTrue((new TestUuid($value))->equals(new TestUuid($value)));
        self::assertFalse((new TestUuid($value))->equals(TestUuid::random()));
    }

    public function test_two_id_types_are_never_equal_even_with_the_same_value(): void
    {
        $value = '0199c0de-1a2b-7c3d-8e4f-5a6b7c8d9e0f';

        self::assertFalse((new TestUuid($value))->equals(new OtherTestUuid($value)));
    }
}

final class TestUuid extends Uuid {}

final class OtherTestUuid extends Uuid {}
