<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Domain\Service;

use App\Modules\Quiz\Domain\ValueObject\AttemptPoints;
use App\Modules\Quiz\Domain\ValueObject\ScoredAnswer;
use App\Modules\Quiz\Domain\ValueObject\Tier;

/**
 * A student's points in one topic, folded from their graded answers in the order they
 * were given. The 0 floor makes order matter, so this is a fold, never a SUM.
 * Attempts of one topic never interleave (one open attempt per student and topic),
 * so each attempt has a well-defined balance before and after.
 */
final readonly class Progress
{
    public const POINTS_PER_ANSWER = 10;

    /** @param array<string, AttemptPoints> $attempts keyed by attempt id */
    private function __construct(
        public int $points,
        private array $attempts,
    ) {}

    /** @param list<ScoredAnswer> $answers in any order */
    public static function fromAnswers(array $answers): self
    {
        usort($answers, static fn (ScoredAnswer $a, ScoredAnswer $b): int => [$a->answeredAt, $a->position, $a->id] <=> [$b->answeredAt, $b->position, $b->id]);

        $points = 0;
        $attempts = [];

        foreach ($answers as $answer) {
            $before = $attempts[$answer->attemptId]->before ?? $points;
            $points = max(0, $points + ($answer->correct ? self::POINTS_PER_ANSWER : -self::POINTS_PER_ANSWER));
            $attempts[$answer->attemptId] = new AttemptPoints($before, $points);
        }

        return new self($points, $attempts);
    }

    public function tier(): Tier
    {
        return Tier::forPoints($this->points);
    }

    public function nextTier(): ?Tier
    {
        return $this->tier()->next();
    }

    /** An attempt with no graded answer yet can only be the newest one: it sits at the current balance. */
    public function forAttempt(string $attemptId): AttemptPoints
    {
        return $this->attempts[$attemptId] ?? new AttemptPoints($this->points, $this->points);
    }
}
