<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Port;

interface TransactionManager
{
    /**
     * @template T
     *
     * @param  callable(): T  $work
     * @return T
     */
    public function run(callable $work): mixed;
}
