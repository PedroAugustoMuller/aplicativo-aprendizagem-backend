<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A classroom holds several subjects; a teacher assignment covers the whole classroom
 * (subject_id null) or one subject of it. Existing rows convert as-is: each classroom
 * keeps its subject and each teacher becomes an assignment of that subject.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classroom_subjects', function (Blueprint $table): void {
            $table->uuid('classroom_id');
            // No foreign key: subjects belong to Content. Validated through SubjectCatalog.
            $table->uuid('subject_id')->index();
            $table->primary(['classroom_id', 'subject_id']);
            $table->foreign('classroom_id')->references('id')->on('classrooms')->cascadeOnDelete();
        });

        DB::statement('INSERT INTO classroom_subjects (classroom_id, subject_id) SELECT id, subject_id FROM classrooms');

        Schema::table('classroom_teachers', function (Blueprint $table): void {
            $table->dropPrimary();
            $table->uuid('id')->nullable();
            $table->uuid('subject_id')->nullable();
        });

        DB::statement('UPDATE classroom_teachers t SET id = gen_random_uuid(), subject_id = c.subject_id FROM classrooms c WHERE c.id = t.classroom_id');

        Schema::table('classroom_teachers', function (Blueprint $table): void {
            $table->uuid('id')->nullable(false)->change();
            $table->primary('id');
            // Nulls are distinct here, so this never clashes with the whole-classroom index below.
            $table->unique(['classroom_id', 'user_id', 'subject_id']);
        });

        DB::statement('CREATE UNIQUE INDEX classroom_teachers_whole_classroom_unique ON classroom_teachers (classroom_id, user_id) WHERE subject_id IS NULL');

        Schema::table('classrooms', function (Blueprint $table): void {
            $table->dropIndex(['subject_id']);
            $table->dropColumn('subject_id');
        });
    }

    /** Development rollback: each classroom keeps one subject; classrooms without any are dropped. */
    public function down(): void
    {
        Schema::table('classrooms', function (Blueprint $table): void {
            $table->uuid('subject_id')->nullable()->index();
        });

        DB::statement('UPDATE classrooms c SET subject_id = (SELECT s.subject_id FROM classroom_subjects s WHERE s.classroom_id = c.id ORDER BY s.subject_id LIMIT 1)');
        DB::table('classrooms')->whereNull('subject_id')->delete();

        Schema::table('classrooms', function (Blueprint $table): void {
            $table->uuid('subject_id')->nullable(false)->change();
        });

        DB::statement('DROP INDEX classroom_teachers_whole_classroom_unique');
        DB::statement('DELETE FROM classroom_teachers a USING classroom_teachers b WHERE a.classroom_id = b.classroom_id AND a.user_id = b.user_id AND a.id > b.id');

        Schema::table('classroom_teachers', function (Blueprint $table): void {
            $table->dropUnique(['classroom_id', 'user_id', 'subject_id']);
            $table->dropPrimary();
            $table->dropColumn(['id', 'subject_id']);
        });

        Schema::table('classroom_teachers', function (Blueprint $table): void {
            $table->primary(['classroom_id', 'user_id']);
        });

        Schema::dropIfExists('classroom_subjects');
    }
};
