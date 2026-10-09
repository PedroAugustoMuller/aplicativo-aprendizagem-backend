<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

final class ScheduleTest extends TestCase
{
    public function test_expired_tokens_are_pruned_daily(): void
    {
        $events = array_values(array_filter(
            $this->app->make(Schedule::class)->events(),
            static fn (Event $event): bool => str_contains((string) $event->command, 'sanctum:prune-expired'),
        ));

        self::assertCount(1, $events);
        self::assertStringContainsString('--hours=24', (string) $events[0]->command);
        self::assertSame('0 0 * * *', $events[0]->expression);
    }
}
