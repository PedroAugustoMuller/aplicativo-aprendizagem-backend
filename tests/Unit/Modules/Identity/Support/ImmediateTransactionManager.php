<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity\Support;

use App\Modules\Identity\Application\Port\TransactionManager;

final class ImmediateTransactionManager implements TransactionManager
{
    public function run(callable $work): mixed
    {
        return $work();
    }
}
