<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Content\Domain;

use App\Modules\Content\Domain\Entity\Subject;
use App\Modules\Content\Domain\ValueObject\SubjectId;
use App\Modules\Content\Domain\ValueObject\SubjectName;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SubjectTest extends TestCase
{
    public function test_it_exposes_its_data(): void
    {
        $id = SubjectId::random();
        $subject = Subject::create($id, new SubjectName('Biologia'));

        self::assertTrue($id->equals($subject->id()));
        self::assertSame('Biologia', $subject->name()->value());
        self::assertTrue($subject->isActive());
        self::assertNull($subject->deactivatedAt());
    }

    public function test_subject_name_trims_surrounding_whitespace(): void
    {
        self::assertSame('Biologia', (new SubjectName('  Biologia  '))->value());
    }

    public function test_subject_name_rejects_an_empty_value(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SubjectName('   ');
    }

    public function test_subject_name_accepts_exactly_the_maximum_length(): void
    {
        self::assertSame(str_repeat('a', 80), (new SubjectName(str_repeat('a', 80)))->value());
    }

    public function test_subject_name_rejects_an_overlong_value(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SubjectName(str_repeat('a', 81));
    }

    public function test_rename_changes_the_name(): void
    {
        $subject = Subject::create(SubjectId::random(), new SubjectName('Biologia'));

        $subject->rename(new SubjectName('Física'));

        self::assertSame('Física', $subject->name()->value());
    }

    public function test_deactivate_is_idempotent_and_keeps_the_first_timestamp(): void
    {
        $subject = Subject::create(SubjectId::random(), new SubjectName('Biologia'));
        $first = new DateTimeImmutable('2026-01-01T00:00:00Z');
        $later = new DateTimeImmutable('2026-06-01T00:00:00Z');

        $subject->deactivate($first);
        $subject->deactivate($later);

        self::assertFalse($subject->isActive());
        self::assertSame($first, $subject->deactivatedAt());
    }
}
