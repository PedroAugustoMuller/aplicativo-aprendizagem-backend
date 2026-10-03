<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Quiz\Support;

use App\Modules\Quiz\Application\Port\TransactionManager;

final class ImmediateTransactionManager implements TransactionManager
{
    public int $runs = 0;

    public function run(callable $work): mixed
    {
        $this->runs++;

        return $work();
    }
}
