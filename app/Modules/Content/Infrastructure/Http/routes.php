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

    // Coarse gate only: whether this staff member authors this subject is the
    // handler's AuthoringGate check (SubjectPolicy::canAuthor).
    Route::middleware('role:staff')->group(function (): void {
        Route::post('/subjects/{id}/topics', [TopicController::class, 'store'])->whereUuid('id');
        Route::patch('/topics/{id}', [TopicController::class, 'update'])->whereUuid('id');
        Route::post('/topics/{id}/deactivate', [TopicController::class, 'deactivate'])->whereUuid('id');
        Route::post('/topics/{id}/reactivate', [TopicController::class, 'reactivate'])->whereUuid('id');
    });
});
