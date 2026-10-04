<?php

declare(strict_types=1);

use App\Modules\Quiz\Infrastructure\Http\Controller\ProgressController;
use App\Modules\Quiz\Infrastructure\Http\Controller\QuizController;
use Illuminate\Support\Facades\Route;

// Coarse gate only: enrolment and ownership are the handlers' QuizAccess checks.
Route::middleware(['auth:sanctum', 'account.active', 'password.changed', 'role:student'])->group(function (): void {
    Route::post('/topics/{id}/quiz-attempts', [QuizController::class, 'start'])->whereUuid('id');
    Route::get('/quiz-attempts/{id}', [QuizController::class, 'show'])->whereUuid('id');
    Route::post('/quiz-attempts/{id}/answers', [QuizController::class, 'answer'])->whereUuid('id');
    Route::get('/subjects/{id}/quiz-progress', [ProgressController::class, 'subject'])->whereUuid('id');
    Route::get('/topics/{id}/quiz-history', [ProgressController::class, 'history'])->whereUuid('id');
    Route::get('/topics/{id}/wrong-questions', [ProgressController::class, 'wrong'])->whereUuid('id');
});

// Staff: which classroom and which student is the handlers' ProgressAccess rule.
Route::middleware(['auth:sanctum', 'account.active', 'password.changed', 'role:staff'])->group(function (): void {
    Route::get('/classrooms/{id}/quiz-progress', [ProgressController::class, 'classroom'])->whereUuid('id');
    Route::get('/classrooms/{id}/students/{studentId}/topics/{topicId}/quiz-history', [ProgressController::class, 'studentHistory'])->whereUuid(['id', 'studentId', 'topicId']);
    Route::get('/classrooms/{id}/students/{studentId}/topics/{topicId}/wrong-questions', [ProgressController::class, 'studentWrong'])->whereUuid(['id', 'studentId', 'topicId']);
    Route::get('/classrooms/{id}/students/{studentId}/quiz-attempts/{attemptId}', [ProgressController::class, 'studentAttempt'])->whereUuid(['id', 'studentId', 'attemptId']);
});
