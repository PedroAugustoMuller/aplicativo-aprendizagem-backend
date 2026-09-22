<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A row exists only while its user still holds a password they did not
        // choose. Encrypted (not hashed) so a teacher can reprint a lost slip.
        Schema::create('pending_credentials', function (Blueprint $table): void {
            $table->uuid('user_id')->primary();
            $table->text('password_encrypted');
            $table->timestamp('created_at');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_credentials');
    }
};
