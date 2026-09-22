<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Modules\Identity\Application\Port\CredentialVault;
use App\Modules\Identity\Application\Port\PasswordHasher;
use App\Modules\Identity\Application\Port\TokenIssuer;
use App\Modules\Identity\Application\Port\TokenRevoker;
use App\Modules\Identity\Application\Port\TransactionManager;
use App\Modules\Identity\Application\Query\ListClassrooms\ClassroomListReader;
use App\Modules\Identity\Application\Query\ListTeachers\AccountListReader;
use App\Modules\Identity\Domain\Repository\ClassroomRepository;
use App\Modules\Identity\Domain\Repository\UserRepository;
use App\Modules\Identity\Infrastructure\Auth\BcryptPasswordHasher;
use App\Modules\Identity\Infrastructure\Auth\EncryptedCredentialVault;
use App\Modules\Identity\Infrastructure\Auth\SanctumTokenIssuer;
use App\Modules\Identity\Infrastructure\Auth\SanctumTokenRevoker;
use App\Modules\Identity\Infrastructure\Console\CreateAdminCommand;
use App\Modules\Identity\Infrastructure\Contract\EloquentTeachingAssignments;
use App\Modules\Identity\Infrastructure\Persistence\DatabaseTransactionManager;
use App\Modules\Identity\Infrastructure\Persistence\EloquentAccountListReader;
use App\Modules\Identity\Infrastructure\Persistence\EloquentClassroomListReader;
use App\Modules\Identity\Infrastructure\Persistence\EloquentClassroomRepository;
use App\Modules\Identity\Infrastructure\Persistence\EloquentUserRepository;
use App\Shared\Domain\Contract\TeachingAssignments;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepository::class, EloquentUserRepository::class);
        $this->app->bind(PasswordHasher::class, BcryptPasswordHasher::class);
        $this->app->bind(TokenIssuer::class, SanctumTokenIssuer::class);
        $this->app->bind(CredentialVault::class, EncryptedCredentialVault::class);
        $this->app->bind(TokenRevoker::class, SanctumTokenRevoker::class);
        $this->app->bind(TransactionManager::class, DatabaseTransactionManager::class);
        $this->app->bind(ClassroomRepository::class, EloquentClassroomRepository::class);
        $this->app->bind(ClassroomListReader::class, EloquentClassroomListReader::class);
        $this->app->bind(AccountListReader::class, EloquentAccountListReader::class);
        $this->app->bind(TeachingAssignments::class, EloquentTeachingAssignments::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([CreateAdminCommand::class]);
        }

        Route::prefix('api/v1')
            ->middleware('api')
            ->group(__DIR__.'/Infrastructure/Http/routes.php');
    }
}
