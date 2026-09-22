<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity\Domain;

use App\Modules\Identity\Domain\Service\UsernameGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UsernameGeneratorTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function names(): iterable
    {
        yield 'first and last' => ['Ana Souza', 'ana.souza'];
        yield 'middle names dropped' => ['Ana Clara de Souza Lima', 'ana.lima'];
        yield 'accents stripped' => ['João Gonçalves', 'joao.goncalves'];
        yield 'single word' => ['Cauã', 'caua'];
        yield 'extra whitespace' => ["  Bia   Lima \t", 'bia.lima'];
        yield 'apostrophe and hyphen' => ["D'Ávila Santos-Neto", 'davila.santosneto'];
    }

    #[DataProvider('names')]
    public function test_it_derives_first_dot_last(string $name, string $expected): void
    {
        self::assertSame($expected, (new UsernameGenerator)->generate($name, fn (): bool => false)->value());
    }

    public function test_collisions_get_the_next_free_number_starting_at_two(): void
    {
        $taken = ['ana.souza', 'ana.souza2'];

        $username = (new UsernameGenerator)->generate('Ana Souza', fn (string $u): bool => in_array($u, $taken, true));

        self::assertSame('ana.souza3', $username->value());
    }

    public function test_a_name_with_no_usable_letters_falls_back_to_aluno(): void
    {
        self::assertSame('aluno', (new UsernameGenerator)->generate('— !!', fn (): bool => false)->value());
    }
}
