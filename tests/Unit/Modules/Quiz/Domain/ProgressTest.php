<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Quiz\Domain;

use App\Modules\Quiz\Domain\Service\Progress;
use App\Modules\Quiz\Domain\ValueObject\ScoredAnswer;
use App\Modules\Quiz\Domain\ValueObject\Tier;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ProgressTest extends TestCase
{
    /**
     * One answer per entry, a minute apart, positions 0..
     *
     * @param  list<bool>  $results
     * @return list<ScoredAnswer>
     */
    private static function attempt(string $id, string $start, array $results): array
    {
        $answers = [];

        foreach ($results as $position => $correct) {
            $answers[] = new ScoredAnswer($id, "$id-$position", $position, $correct, (new DateTimeImmutable($start))->modify("+$position minutes"));
        }

        return $answers;
    }

    public function test_no_answers_is_zero_points_in_iron(): void
    {
        $progress = Progress::fromAnswers([]);

        self::assertSame(0, $progress->points);
        self::assertSame(Tier::Iron, $progress->tier());
        self::assertSame(Tier::Bronze, $progress->nextTier());
    }

    public function test_right_adds_ten_and_wrong_takes_ten(): void
    {
        $progress = Progress::fromAnswers(self::attempt('a', '2026-10-01 10:00', [true, true, true, false, true]));

        self::assertSame(30, $progress->points);
    }

    public function test_the_balance_never_goes_below_zero(): void
    {
        // 10, 0, 0, 10: the second wrong answer had nothing left to take.
        $progress = Progress::fromAnswers(self::attempt('a', '2026-10-01 10:00', [true, false, false, true]));

        self::assertSame(10, $progress->points);
    }

    public function test_a_wrong_only_quiz_at_zero_changes_nothing(): void
    {
        $progress = Progress::fromAnswers(self::attempt('a', '2026-10-01 10:00', array_fill(0, 10, false)));

        self::assertSame(0, $progress->forAttempt('a')->before);
        self::assertSame(0, $progress->forAttempt('a')->after);
        self::assertSame(0, $progress->forAttempt('a')->change());
    }

    public function test_it_orders_by_answered_at_not_by_input_order(): void
    {
        // Given out of order (a late offline sync): the early wrong answer is floored at 0,
        // then the right one adds 10. In input order it would be 10 - 10 = 0.
        $late = new ScoredAnswer('a', 'a-1', 1, true, new DateTimeImmutable('2026-10-01 10:05'));
        $early = new ScoredAnswer('a', 'a-0', 0, false, new DateTimeImmutable('2026-10-01 10:00'));

        self::assertSame(10, Progress::fromAnswers([$late, $early])->points);
    }

    public function test_equal_times_fall_back_to_position_then_id(): void
    {
        $at = new DateTimeImmutable('2026-10-01 10:00');

        // position 0 (wrong, floored) then position 1 (right) = 10.
        self::assertSame(10, Progress::fromAnswers([new ScoredAnswer('a', 'x', 1, true, $at), new ScoredAnswer('a', 'y', 0, false, $at)])->points);
        // Same position too: by id, "p" (right) before "q" (wrong) = 0.
        self::assertSame(0, Progress::fromAnswers([new ScoredAnswer('a', 'q', 0, false, $at), new ScoredAnswer('b', 'p', 0, true, $at)])->points);
    }

    public function test_each_attempt_knows_its_before_and_after(): void
    {
        $first = self::attempt('a', '2026-10-01 10:00', array_fill(0, 10, true));
        $second = self::attempt('b', '2026-10-02 10:00', [true, true, true, true, true, true, true, false, false, false]);

        $progress = Progress::fromAnswers([...$second, ...$first]);

        self::assertSame(140, $progress->points);
        self::assertSame(Tier::Bronze, $progress->tier());
        self::assertSame([0, 100, 100], [$progress->forAttempt('a')->before, $progress->forAttempt('a')->after, $progress->forAttempt('a')->change()]);
        self::assertSame([100, 140, 40], [$progress->forAttempt('b')->before, $progress->forAttempt('b')->after, $progress->forAttempt('b')->change()]);
    }

    public function test_an_attempt_without_answers_sits_at_the_current_balance(): void
    {
        $progress = Progress::fromAnswers(self::attempt('a', '2026-10-01 10:00', [true, true]));

        self::assertSame(20, $progress->forAttempt('open-and-empty')->before);
        self::assertSame(20, $progress->forAttempt('open-and-empty')->after);
    }

    public function test_diamond_has_no_next_tier(): void
    {
        $answers = [];

        for ($i = 0; $i < 9; $i++) {
            $answers = [...$answers, ...self::attempt("a$i", '2026-10-0'.($i + 1).' 10:00', array_fill(0, 10, true))];
        }

        $progress = Progress::fromAnswers($answers);

        self::assertSame(900, $progress->points);
        self::assertSame(Tier::Diamond, $progress->tier());
        self::assertNull($progress->nextTier());
    }
}
