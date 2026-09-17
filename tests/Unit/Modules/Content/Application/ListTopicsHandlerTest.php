<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Content\Application;

use App\Modules\Content\Application\Query\ListTopics\ListTopicsHandler;
use App\Modules\Content\Application\Query\ListTopics\ListTopicsQuery;
use App\Modules\Content\Application\Query\ListTopics\TopicListItem;
use App\Modules\Content\Application\Query\ListTopics\TopicListReader;
use PHPUnit\Framework\TestCase;

final class ListTopicsHandlerTest extends TestCase
{
    public function test_it_returns_what_the_reader_provides(): void
    {
        $reader = new class implements TopicListReader
        {
            public function all(): array
            {
                return [
                    new TopicListItem('0199c0de-1a2b-7c3d-8e4f-5a6b7c8d9e0f', 'Átomos', 'Estrutura atômica.', 2),
                ];
            }
        };

        $items = (new ListTopicsHandler($reader))->handle(new ListTopicsQuery);

        self::assertCount(1, $items);
        self::assertSame('Átomos', $items[0]->name);
        self::assertSame(2, $items[0]->position);
    }

    public function test_it_returns_an_empty_list_when_there_are_no_topics(): void
    {
        $reader = new class implements TopicListReader
        {
            public function all(): array
            {
                return [];
            }
        };

        self::assertSame([], (new ListTopicsHandler($reader))->handle(new ListTopicsQuery));
    }
}
