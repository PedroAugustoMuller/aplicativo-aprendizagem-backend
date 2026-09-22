<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Http\Controller\AuthController;
use App\Modules\Identity\Infrastructure\Http\Controller\ClassroomController;
use App\Modules\Identity\Infrastructure\Http\Controller\StudentController;
use App\Modules\Identity\Infrastructure\Http\Controller\TeacherController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('auth.login');

Route::middleware(['auth:sanctum', 'account.active', 'password.changed'])->group(function (): void {
    Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::put('/auth/password', [AuthController::class, 'changePassword'])
        ->middleware('throttle:password')
        ->name('auth.password');

    Route::get('/classrooms', [ClassroomController::class, 'index']);

    Route::middleware('role:admin')->group(function (): void {
        Route::post('/classrooms', [ClassroomController::class, 'store']);
        Route::patch('/classrooms/{id}', [ClassroomController::class, 'update'])->whereUuid('id');
        Route::put('/classrooms/{id}/teachers', [ClassroomController::class, 'assignTeachers'])->whereUuid('id');
        Route::post('/classrooms/{id}/deactivate', [ClassroomController::class, 'deactivate'])->whereUuid('id');

        Route::get('/teachers', [TeacherController::class, 'index']);
        Route::post('/teachers', [TeacherController::class, 'store']);
        Route::post('/teachers/{id}/reset-password', [TeacherController::class, 'resetPassword'])->whereUuid('id');
        Route::post('/teachers/{id}/deactivate', [TeacherController::class, 'deactivate'])->whereUuid('id');
        Route::post('/teachers/{id}/reactivate', [TeacherController::class, 'reactivate'])->whereUuid('id');
    });

    Route::middleware('role:staff')->group(function (): void {
        Route::get('/classrooms/{id}/students', [StudentController::class, 'index'])->whereUuid('id');
        Route::post('/classrooms/{id}/students', [StudentController::class, 'store'])->whereUuid('id');
        Route::get('/classrooms/{id}/credentials', [StudentController::class, 'credentials'])->whereUuid('id');
        Route::put('/classrooms/{id}/students/{studentId}', [StudentController::class, 'enrol'])->whereUuid(['id', 'studentId']);
        Route::delete('/classrooms/{id}/students/{studentId}', [StudentController::class, 'unenrol'])->whereUuid(['id', 'studentId']);
        Route::post('/students/{id}/reset-password', [StudentController::class, 'resetPassword'])->whereUuid('id');
        Route::post('/students/{id}/deactivate', [StudentController::class, 'deactivate'])->whereUuid('id');
        Route::post('/students/{id}/reactivate', [StudentController::class, 'reactivate'])->whereUuid('id');
    });
});
