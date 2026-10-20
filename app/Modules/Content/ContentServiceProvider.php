<?php

declare(strict_types=1);

namespace App\Modules\Content;

use App\Modules\Content\Application\Command\SuggestQuestions\QuestionGenerator;
use App\Modules\Content\Application\Query\ListQuestions\QuestionBankReader;
use App\Modules\Content\Application\Query\ListSubjects\SubjectListReader;
use App\Modules\Content\Application\Query\ListTopics\TopicListReader;
use App\Modules\Content\Domain\Repository\QuestionRepository;
use App\Modules\Content\Domain\Repository\SubjectRepository;
use App\Modules\Content\Domain\Repository\TopicRepository;
use App\Modules\Content\Infrastructure\Ai\DisabledQuestionGenerator;
use App\Modules\Content\Infrastructure\Ai\FakeQuestionGenerator;
use App\Modules\Content\Infrastructure\Contract\EloquentQuestionBank;
use App\Modules\Content\Infrastructure\Contract\EloquentSubjectCatalog;
use App\Modules\Content\Infrastructure\Persistence\EloquentQuestionBankReader;
use App\Modules\Content\Infrastructure\Persistence\EloquentQuestionRepository;
use App\Modules\Content\Infrastructure\Persistence\EloquentSubjectListReader;
use App\Modules\Content\Infrastructure\Persistence\EloquentSubjectRepository;
use App\Modules\Content\Infrastructure\Persistence\EloquentTopicListReader;
use App\Modules\Content\Infrastructure\Persistence\EloquentTopicRepository;
use App\Shared\Domain\Contract\QuestionBank;
use App\Shared\Domain\Contract\SubjectCatalog;
use App\Shared\Infrastructure\AiSuggestions;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class ContentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TopicRepository::class, EloquentTopicRepository::class);
        $this->app->bind(TopicListReader::class, EloquentTopicListReader::class);
        $this->app->bind(QuestionBankReader::class, EloquentQuestionBankReader::class);
        $this->app->bind(QuestionRepository::class, EloquentQuestionRepository::class);
        $this->app->bind(SubjectRepository::class, EloquentSubjectRepository::class);
        $this->app->bind(SubjectListReader::class, EloquentSubjectListReader::class);
        $this->app->bind(SubjectCatalog::class, EloquentSubjectCatalog::class);
        $this->app->bind(QuestionBank::class, EloquentQuestionBank::class);

        // Resolved per request from config, so tests (and a changed .env) pick the provider.
        $this->app->bind(QuestionGenerator::class, static fn (Application $app): QuestionGenerator => match (AiSuggestions::provider()) {
            'gemini' => new DisabledQuestionGenerator, // Task 8 replaces this arm
            'fake' => new FakeQuestionGenerator,
            default => new DisabledQuestionGenerator,
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        Route::prefix('api/v1')
            ->middleware('api')
            ->group(__DIR__.'/Infrastructure/Http/routes.php');
    }
}
