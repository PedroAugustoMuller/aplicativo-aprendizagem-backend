<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity\Domain;

use App\Modules\Identity\Domain\Service\TemporaryPasswordGenerator;
use PHPUnit\Framework\TestCase;

final class TemporaryPasswordGeneratorTest extends TestCase
{
    public function test_it_uses_only_unambiguous_characters_and_is_eight_long(): void
    {
        $generator = new TemporaryPasswordGenerator;

        for ($i = 0; $i < 500; $i++) {
            $password = $generator->generate();
            self::assertSame(8, strlen($password));
            self::assertMatchesRegularExpression('/^[ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789]{8}$/', $password);
        }
    }

    public function test_it_does_not_repeat_itself(): void
    {
        $generator = new TemporaryPasswordGenerator;
        $seen = [];
        for ($i = 0; $i < 200; $i++) {
            $seen[$generator->generate()] = true;
        }

        self::assertCount(200, $seen);
    }
}
