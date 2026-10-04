<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Domain\ValueObject;

/** A student's level in one topic, from fixed point thresholds. Cases are in ascending order. */
enum Tier: string
{
    case Iron = 'iron';
    case Bronze = 'bronze';
    case Silver = 'silver';
    case Gold = 'gold';
    case Emerald = 'emerald';
    case Diamond = 'diamond';

    public function threshold(): int
    {
        return match ($this) {
            self::Iron => 0,
            self::Bronze => 50,
            self::Silver => 150,
            self::Gold => 300,
            self::Emerald => 500,
            self::Diamond => 800,
        };
    }

    public static function forPoints(int $points): self
    {
        $tier = self::Iron;

        foreach (self::cases() as $case) {
            if ($points >= $case->threshold()) {
                $tier = $case;
            }
        }

        return $tier;
    }

    public function next(): ?self
    {
        $found = false;

        foreach (self::cases() as $case) {
            if ($found) {
                return $case;
            }

            $found = $case === $this;
        }

        return null;
    }
}
