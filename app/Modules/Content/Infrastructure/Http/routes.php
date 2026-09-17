<?php

declare(strict_types=1);

use App\Modules\Content\Infrastructure\Http\Controller\TopicController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/topics', [TopicController::class, 'index']);
});
