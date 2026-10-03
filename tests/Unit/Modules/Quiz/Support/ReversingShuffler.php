<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Quiz\Support;

use App\Modules\Quiz\Domain\Service\Shuffler;

/** Deterministic: reversing proves a shuffle was applied without depending on chance. */
final class ReversingShuffler implements Shuffler
{
    public function shuffle(array $items): array
    {
        return array_reverse($items);
    }
}
