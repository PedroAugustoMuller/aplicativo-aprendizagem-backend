<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classrooms', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 80)->unique();
            // No foreign key: subjects belong to Content. Validated through SubjectCatalog.
            $table->uuid('subject_id')->index();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('classroom_teachers', function (Blueprint $table): void {
            $table->uuid('classroom_id');
            $table->uuid('user_id');
            $table->primary(['classroom_id', 'user_id']);
            $table->foreign('classroom_id')->references('id')->on('classrooms')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users');
            $table->index('user_id');
        });

        Schema::create('classroom_students', function (Blueprint $table): void {
            $table->uuid('classroom_id');
            $table->uuid('user_id');
            $table->primary(['classroom_id', 'user_id']);
            $table->foreign('classroom_id')->references('id')->on('classrooms')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classroom_students');
        Schema::dropIfExists('classroom_teachers');
        Schema::dropIfExists('classrooms');
    }
};
