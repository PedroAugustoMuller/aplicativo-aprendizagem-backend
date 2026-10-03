<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('topic_id');
            $table->string('type', 20);
            $table->text('statement');
            $table->text('explanation')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();

            $table->foreign('topic_id')->references('id')->on('topics');
            $table->index(['topic_id', 'created_at']);
        });

        Schema::create('question_options', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('question_id');
            $table->string('text', 200);
            $table->boolean('is_correct');
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            $table->foreign('question_id')->references('id')->on('questions')->cascadeOnDelete();
            $table->unique(['question_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_options');
        Schema::dropIfExists('questions');
    }
};
