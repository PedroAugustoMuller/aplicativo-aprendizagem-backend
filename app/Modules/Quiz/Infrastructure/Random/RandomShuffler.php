<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Infrastructure\Random;

use App\Modules\Quiz\Domain\Service\Shuffler;
use Random\Randomizer;

final class RandomShuffler implements Shuffler
{
    public function shuffle(array $items): array
    {
        // Randomizer defaults to a CSPRNG engine; no global seed state is touched.
        return array_values((new Randomizer)->shuffleArray($items));
    }
}
