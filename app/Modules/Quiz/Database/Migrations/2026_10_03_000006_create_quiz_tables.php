<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_attempts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('student_id');
            $table->uuid('topic_id');
            // No foreign key, like classrooms.subject_id: the subjects migration must stay
            // reversible (SubjectMigrationTest rolls it back with later tables in place).
            $table->uuid('subject_id')->index();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('student_id')->references('id')->on('users');
            $table->foreign('topic_id')->references('id')->on('topics');
            $table->index(['student_id', 'topic_id']);
        });

        // Backstop for the start lock: at most one open attempt per student and topic.
        DB::statement('CREATE UNIQUE INDEX quiz_attempts_one_open ON quiz_attempts (student_id, topic_id) WHERE completed_at IS NULL');

        Schema::create('quiz_attempt_questions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('attempt_id');
            $table->unsignedSmallInteger('position');
            // Links back to the bank while it exists; the snapshot below is what counts.
            $table->uuid('question_id')->nullable();
            $table->string('type', 20);
            $table->text('statement');
            $table->text('explanation')->nullable();
            $table->json('options');
            $table->uuid('correct_option_id');
            $table->uuid('answer_id')->nullable()->unique();
            $table->uuid('chosen_option_id')->nullable();
            $table->uuid('option_id')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            $table->foreign('attempt_id')->references('id')->on('quiz_attempts')->cascadeOnDelete();
            $table->foreign('question_id')->references('id')->on('questions')->nullOnDelete();
            $table->foreign('option_id')->references('id')->on('question_options')->nullOnDelete();
            $table->unique(['attempt_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_attempt_questions');
        Schema::dropIfExists('quiz_attempts');
    }
};
