<?php

declare(strict_types=1);

use App\Modules\Content\Infrastructure\Http\Controller\SubjectController;
use App\Modules\Content\Infrastructure\Http\Controller\TopicController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'account.active', 'password.changed'])->group(function (): void {
    Route::get('/subjects', [SubjectController::class, 'index']);
    Route::get('/subjects/{id}/topics', [TopicController::class, 'index'])->whereUuid('id');

    Route::middleware('role:admin')->group(function (): void {
        Route::post('/subjects', [SubjectController::class, 'store']);
        Route::patch('/subjects/{id}', [SubjectController::class, 'update'])->whereUuid('id');
        Route::post('/subjects/{id}/deactivate', [SubjectController::class, 'deactivate'])->whereUuid('id');
    });
});
