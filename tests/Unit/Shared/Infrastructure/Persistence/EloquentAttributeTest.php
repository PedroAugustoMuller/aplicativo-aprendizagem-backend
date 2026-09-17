<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Persistence;

use App\Shared\Infrastructure\Persistence\EloquentAttribute;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class EloquentAttributeTest extends TestCase
{
    public function test_a_string_value_passes_through_unchanged(): void
    {
        self::assertSame('ana@escola.br', EloquentAttribute::string('ana@escola.br', 'users.email'));
    }

    public function test_a_non_string_value_throws_with_the_context_in_the_message(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Expected a string for topics.id.');

        EloquentAttribute::string(42, 'topics.id');
    }
}
