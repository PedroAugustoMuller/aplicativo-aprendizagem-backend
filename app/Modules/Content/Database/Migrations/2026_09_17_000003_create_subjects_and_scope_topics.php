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
        Schema::create('subjects', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 80)->unique();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();
        });

        Schema::table('topics', function (Blueprint $table): void {
            $table->uuid('subject_id')->nullable()->after('id');
        });

        // Every topic that exists predates subjects and is chemistry. This literal
        // must match App\Modules\Content\Database\Seeders\SubjectsSeeder::CHEMISTRY_ID
        // exactly — the migration cannot import that class (deptrac forbids a
        // migration depending on a module's seeder, and migrations must stay
        // portable on their own) but `db:seed` inserts Química with that fixed id.
        // Backfilling with a random id here would collide with the seeder's insert
        // on `subjects.name`'s unique constraint on any database that already had
        // topics before this migration ran.
        if (DB::table('topics')->exists()) {
            $chemistry = '0192f0a0-0000-7000-8000-000000000001';
            DB::table('subjects')->insert([
                'id' => $chemistry, 'name' => 'Química', 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('topics')->update(['subject_id' => $chemistry]);
        }

        Schema::table('topics', function (Blueprint $table): void {
            $table->uuid('subject_id')->nullable(false)->change();
            $table->foreign('subject_id')->references('id')->on('subjects');
            $table->unique(['subject_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('topics', function (Blueprint $table): void {
            $table->dropUnique(['subject_id', 'name']);
            $table->dropForeign(['subject_id']);
            $table->dropColumn('subject_id');
        });

        Schema::dropIfExists('subjects');
    }
};
