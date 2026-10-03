<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Domain\Service;

/** Randomness is injected so the aggregate stays deterministic under test. */
interface Shuffler
{
    /**
     * @template T
     *
     * @param  list<T>  $items
     * @return list<T>
     */
    public function shuffle(array $items): array;
}
