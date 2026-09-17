<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity\Domain;

use App\Modules\Identity\Domain\ValueObject\Email;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EmailTest extends TestCase
{
    public function test_it_normalises_case_and_whitespace(): void
    {
        self::assertSame('teacher@escola.br', (new Email('  Teacher@Escola.BR '))->value());
    }

    public function test_it_rejects_a_malformed_address(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Email('not-an-email');
    }

    public function test_it_rejects_an_empty_address(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Email('   ');
    }

    public function test_equality_ignores_case(): void
    {
        self::assertTrue((new Email('a@b.com'))->equals(new Email('A@B.com')));
    }
}
