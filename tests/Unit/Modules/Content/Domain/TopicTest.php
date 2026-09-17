<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Content\Domain;

use App\Modules\Content\Domain\Entity\Topic;
use App\Modules\Content\Domain\ValueObject\TopicId;
use App\Modules\Content\Domain\ValueObject\TopicName;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TopicTest extends TestCase
{
    public function test_it_exposes_its_data(): void
    {
        $id = TopicId::random();
        $topic = new Topic($id, new TopicName('Ligações Químicas'), 'Iônicas, covalentes e metálicas.', 4);

        self::assertTrue($id->equals($topic->id()));
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

        new Topic(TopicId::random(), new TopicName('Átomos'), '', -1);
    }
}
