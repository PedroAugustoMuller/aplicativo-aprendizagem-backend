<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Quiz\Domain;

use App\Modules\Quiz\Domain\ValueObject\Tier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TierTest extends TestCase
{
    /** @return iterable<string, array{int, Tier}> */
    public static function boundaries(): iterable
    {
        yield '0' => [0, Tier::Iron];
        yield '49' => [49, Tier::Iron];
        yield '50' => [50, Tier::Bronze];
        yield '149' => [149, Tier::Bronze];
        yield '150' => [150, Tier::Silver];
        yield '299' => [299, Tier::Silver];
        yield '300' => [300, Tier::Gold];
        yield '499' => [499, Tier::Gold];
        yield '500' => [500, Tier::Emerald];
        yield '799' => [799, Tier::Emerald];
        yield '800' => [800, Tier::Diamond];
        yield '5000' => [5000, Tier::Diamond];
    }

    #[DataProvider('boundaries')]
    public function test_points_map_to_their_tier(int $points, Tier $tier): void
    {
        self::assertSame($tier, Tier::forPoints($points));
    }

    public function test_each_tier_knows_the_next_one(): void
    {
        self::assertSame(Tier::Bronze, Tier::Iron->next());
        self::assertSame(Tier::Diamond, Tier::Emerald->next());
        self::assertNull(Tier::Diamond->next());
    }

    public function test_codes_and_thresholds_are_the_agreed_ones(): void
    {
        self::assertSame(
            ['iron' => 0, 'bronze' => 50, 'silver' => 150, 'gold' => 300, 'emerald' => 500, 'diamond' => 800],
            array_combine(
                array_map(static fn (Tier $t): string => $t->value, Tier::cases()),
                array_map(static fn (Tier $t): int => $t->threshold(), Tier::cases()),
            ),
        );
    }
}
