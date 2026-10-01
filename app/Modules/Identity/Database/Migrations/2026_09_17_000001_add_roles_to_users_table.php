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
        Schema::table('users', function (Blueprint $table): void {
            // Existing rows predate roles; the only one is the seeded teacher,
            // who becomes the first admin.
            $table->string('role', 16)->default('admin');
            $table->string('username', 60)->nullable()->unique();
            $table->string('email')->nullable()->change();
            $table->boolean('must_change_password')->default(false);
            $table->timestamp('deactivated_at')->nullable();
        });

        // Remove the default so a new row must state its role explicitly.
        DB::statement('ALTER TABLE users ALTER COLUMN role DROP DEFAULT');

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_valid CHECK (role IN ('admin', 'teacher', 'student'))");
        DB::statement(<<<'SQL'
            ALTER TABLE users ADD CONSTRAINT users_login_matches_role CHECK (
                (role = 'student' AND username IS NOT NULL AND email IS NULL)
                OR (role <> 'student' AND email IS NOT NULL AND username IS NULL)
            )
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_login_matches_role');
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_valid');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['role', 'username', 'must_change_password', 'deactivated_at']);
            $table->string('email')->nullable(false)->change();
        });
    }
};
