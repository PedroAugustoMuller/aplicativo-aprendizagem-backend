<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity\Domain;

use App\Modules\Identity\Domain\ValueObject\Username;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UsernameTest extends TestCase
{
    public function test_it_accepts_a_generated_shape(): void
    {
        self::assertSame('ana.souza2', (new Username('ana.souza2'))->value());
    }

    public function test_it_lowercases_and_trims_what_a_student_types(): void
    {
        self::assertSame('ana.souza', (new Username('  Ana.Souza '))->value());
    }

    /** @return iterable<string, array{string}> */
    public static function invalid(): iterable
    {
        yield 'empty' => [''];
        yield 'space inside' => ['ana souza'];
        yield 'accent' => ['joão'];
        yield 'leading dot' => ['.ana'];
        yield 'email' => ['ana@escola.br'];
        yield 'too long' => [str_repeat('a', 61)];
    }

    #[DataProvider('invalid')]
    public function test_it_rejects_malformed_usernames(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Username($value);
    }
}
