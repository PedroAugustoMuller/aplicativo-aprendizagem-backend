<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Application\Port;

/** Quiz's twin of Identity's port — a module never imports another module's internals. */
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
