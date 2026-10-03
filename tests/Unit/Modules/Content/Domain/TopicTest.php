<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Content\Domain;

use App\Modules\Content\Domain\Entity\Topic;
use App\Modules\Content\Domain\ValueObject\SubjectId;
use App\Modules\Content\Domain\ValueObject\TopicId;
use App\Modules\Content\Domain\ValueObject\TopicName;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TopicTest extends TestCase
{
    public function test_it_exposes_its_data(): void
    {
        $id = TopicId::random();
        $subjectId = SubjectId::random();
        $topic = new Topic($id, $subjectId, new TopicName('Ligações Químicas'), 'Iônicas, covalentes e metálicas.', 4);

        self::assertTrue($id->equals($topic->id()));
        self::assertTrue($subjectId->equals($topic->subjectId()));
        self::assertSame('Ligações Químicas', $topic->name()->value());
        self::assertSame('Iônicas, covalentes e metálicas.', $topic->description());
        self::assertSame(4, $topic->position());
    }

    public function test_topic_name_trims_surrounding_whitespace(): void
    {
        self::assertSame('Tabela Periódica', (new TopicName('  Tabela Periódica  '))->value());
    }

    public function test_topic_name_rejects_an_empty_value(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TopicName('   ');
    }

    public function test_topic_name_accepts_exactly_the_maximum_length(): void
    {
        self::assertSame(str_repeat('a', 120), (new TopicName(str_repeat('a', 120)))->value());
    }

    public function test_topic_name_rejects_an_overlong_value(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TopicName(str_repeat('a', 121));
    }

    public function test_a_negative_position_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Topic(TopicId::random(), SubjectId::random(), new TopicName('Átomos'), '', -1);
    }

    public function test_a_new_topic_is_new_and_a_restored_one_is_not(): void
    {
        $new = new Topic(TopicId::random(), SubjectId::random(), new TopicName('Átomos'), '', 0);
        $restored = Topic::restore(TopicId::random(), SubjectId::random(), new TopicName('Átomos'), '', 0, null);

        self::assertTrue($new->isNew());
        self::assertFalse($restored->isNew());
        self::assertFalse($restored->detailsChanged());
        self::assertFalse($restored->activationChanged());
        self::assertTrue($restored->isActive());
    }

    public function test_renaming_and_describing_mark_only_the_details_as_changed(): void
    {
        $topic = Topic::restore(TopicId::random(), SubjectId::random(), new TopicName('Átomos'), 'Velha.', 0, null);

        $topic->rename(new TopicName('Átomos e Íons'));
        $topic->changeDescription('  Nova.  ');

        self::assertSame('Átomos e Íons', $topic->name()->value());
        self::assertSame('Nova.', $topic->description());
        self::assertTrue($topic->detailsChanged());
        self::assertFalse($topic->activationChanged());
    }

    public function test_a_description_longer_than_500_characters_is_rejected(): void
    {
        $topic = Topic::restore(TopicId::random(), SubjectId::random(), new TopicName('Átomos'), '', 0, null);

        $this->expectException(InvalidArgumentException::class);

        $topic->changeDescription(str_repeat('a', 501));
    }

    public function test_deactivating_twice_keeps_the_first_moment_and_reactivating_clears_it(): void
    {
        $topic = Topic::restore(TopicId::random(), SubjectId::random(), new TopicName('Átomos'), '', 0, null);
        $first = new DateTimeImmutable('2026-10-02 10:00:00');

        $topic->deactivate($first);
        $topic->deactivate(new DateTimeImmutable('2026-10-02 11:00:00'));

        self::assertFalse($topic->isActive());
        self::assertSame($first, $topic->deactivatedAt());
        self::assertTrue($topic->activationChanged());
        self::assertFalse($topic->detailsChanged());

        $topic->reactivate();

        self::assertTrue($topic->isActive());
        self::assertNull($topic->deactivatedAt());
    }
}
