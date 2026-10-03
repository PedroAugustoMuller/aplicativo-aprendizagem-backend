<?php

declare(strict_types=1);

use App\Modules\Quiz\Infrastructure\Http\Controller\QuizController;
use Illuminate\Support\Facades\Route;

// Coarse gate only: enrolment and ownership are the handlers' QuizAccess checks.
Route::middleware(['auth:sanctum', 'account.active', 'password.changed', 'role:student'])->group(function (): void {
    Route::post('/topics/{id}/quiz-attempts', [QuizController::class, 'start'])->whereUuid('id');
    Route::get('/quiz-attempts/{id}', [QuizController::class, 'show'])->whereUuid('id');
    Route::post('/quiz-attempts/{id}/answers', [QuizController::class, 'answer'])->whereUuid('id');
});
