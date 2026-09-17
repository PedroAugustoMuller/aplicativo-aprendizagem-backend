<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Modules\Identity\Application\Port\PasswordHasher;
use App\Modules\Identity\Application\Port\TokenIssuer;
use App\Modules\Identity\Domain\Repository\UserRepository;
use App\Modules\Identity\Infrastructure\Auth\BcryptPasswordHasher;
use App\Modules\Identity\Infrastructure\Auth\SanctumTokenIssuer;
use App\Modules\Identity\Infrastructure\Persistence\EloquentUserRepository;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepository::class, EloquentUserRepository::class);
        $this->app->bind(PasswordHasher::class, BcryptPasswordHasher::class);
        $this->app->bind(TokenIssuer::class, SanctumTokenIssuer::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        Route::prefix('api/v1')
            ->middleware('api')
            ->group(__DIR__.'/Infrastructure/Http/routes.php');
    }
}
