<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Infrastructure\Persistence;

use App\Modules\Quiz\Application\Port\TransactionManager;
use Illuminate\Support\Facades\DB;

final class DatabaseTransactionManager implements TransactionManager
{
    public function run(callable $work): mixed
    {
        return DB::transaction(static fn (): mixed => $work());
    }
}
