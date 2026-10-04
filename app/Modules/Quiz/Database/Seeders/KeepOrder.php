<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Database\Seeders;

use App\Modules\Quiz\Domain\Service\Shuffler;

/** Seed data must come out the same on every run: draw questions and options in bank order. */
final class KeepOrder implements Shuffler
{
    /**
     * @template T
     *
     * @param  list<T>  $items
     * @return list<T>
     */
    public function shuffle(array $items): array
    {
        return $items;
    }
}
