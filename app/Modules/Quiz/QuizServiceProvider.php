<?php

declare(strict_types=1);

namespace App\Modules\Quiz;

use App\Modules\Quiz\Application\Port\TransactionManager;
use App\Modules\Quiz\Domain\Repository\AttemptRepository;
use App\Modules\Quiz\Domain\Service\Shuffler;
use App\Modules\Quiz\Infrastructure\Persistence\DatabaseTransactionManager;
use App\Modules\Quiz\Infrastructure\Persistence\EloquentAttemptRepository;
use App\Modules\Quiz\Infrastructure\Random\RandomShuffler;
use Illuminate\Support\ServiceProvider;

final class QuizServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AttemptRepository::class, EloquentAttemptRepository::class);
        $this->app->bind(TransactionManager::class, DatabaseTransactionManager::class);
        $this->app->bind(Shuffler::class, RandomShuffler::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
    }
}
