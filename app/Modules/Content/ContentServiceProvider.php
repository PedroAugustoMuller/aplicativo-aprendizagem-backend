<?php

declare(strict_types=1);

namespace App\Modules\Content;

use App\Modules\Content\Application\Query\ListTopics\TopicListReader;
use App\Modules\Content\Domain\Repository\TopicRepository;
use App\Modules\Content\Infrastructure\Persistence\EloquentTopicListReader;
use App\Modules\Content\Infrastructure\Persistence\EloquentTopicRepository;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class ContentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TopicRepository::class, EloquentTopicRepository::class);
        $this->app->bind(TopicListReader::class, EloquentTopicListReader::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        Route::prefix('api/v1')
            ->middleware('api')
            ->group(__DIR__.'/Infrastructure/Http/routes.php');
    }
}
