<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Content;

use App\Modules\Content\Database\Seeders\ChemistryTopicsSeeder;
use App\Modules\Content\Database\Seeders\SubjectsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

final class SubjectMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_topics_are_assigned_to_quimica(): void
    {
        // By path, not --step: a later migration (classrooms) now sits on top of this
        // one in the batch. Rolling back "the last migration" would hit that one
        // instead of subjects, which is what this test actually needs to undo.
        $this->artisanCommand('migrate:rollback', [
            '--path' => 'app/Modules/Content/Database/Migrations/2026_09_17_000003_create_subjects_and_scope_topics.php',
        ])->assertSuccessful();

        DB::table('topics')->insert([
            'id' => (string) Str::uuid7(), 'name' => 'Tabela Periódica', 'description' => 'x', 'position' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisanCommand('migrate')->assertSuccessful();

        $subjectId = DB::table('subjects')->where('name', 'Química')->value('id');
        self::assertIsString($subjectId);
        self::assertSame($subjectId, DB::table('topics')->where('name', 'Tabela Periódica')->value('subject_id'));
    }

    /**
     * On any database upgraded from the foundation (instead of recreated from
     * scratch), topics already exist when this migration runs, so it backfills a
     * Química subject row itself. The seeders that run afterwards must not then
     * try to insert a second, colliding Química — the backfilled row's id must be
     * the exact fixed id SubjectsSeeder::CHEMISTRY_ID uses, so `db:seed` treats it
     * as the same subject rather than tripping the `subjects.name` unique index.
     */
    public function test_seeding_after_a_backfilled_migration_reuses_the_fixed_chemistry_id(): void
    {
        $this->artisanCommand('migrate:rollback', [
            '--path' => 'app/Modules/Content/Database/Migrations/2026_09_17_000003_create_subjects_and_scope_topics.php',
        ])->assertSuccessful();

        DB::table('topics')->insert([
            'id' => (string) Str::uuid7(), 'name' => 'Tabela Periódica', 'description' => 'x', 'position' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisanCommand('migrate')->assertSuccessful();

        $this->seed(SubjectsSeeder::class);
        $this->seed(ChemistryTopicsSeeder::class);

        $subjectId = DB::table('subjects')->where('name', 'Química')->value('id');
        self::assertSame(SubjectsSeeder::CHEMISTRY_ID, $subjectId);
        self::assertSame(
            SubjectsSeeder::CHEMISTRY_ID,
            DB::table('topics')->where('name', 'Tabela Periódica')->value('subject_id'),
        );
        self::assertSame(
            SubjectsSeeder::CHEMISTRY_ID,
            DB::table('topics')->where('name', 'Ligações Químicas')->value('subject_id'),
        );
    }

    public function test_the_same_topic_name_is_allowed_in_two_subjects_but_not_twice_in_one(): void
    {
        $a = $this->subject('Química');
        $b = $this->subject('Biologia');
        $this->topic($a, 'Introdução');
        $this->topic($b, 'Introdução');

        $this->expectException(QueryException::class);
        $this->topic($a, 'Introdução');
    }

    private function subject(string $name): string
    {
        $id = (string) Str::uuid7();

        DB::table('subjects')->insert([
            'id' => $id, 'name' => $name, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    private function topic(string $subjectId, string $name): void
    {
        DB::table('topics')->insert([
            'id' => (string) Str::uuid7(),
            'subject_id' => $subjectId,
            'name' => $name,
            'description' => 'x',
            'position' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * artisan() is typed PendingCommand|int because it returns a bare exit code
     * when console output mocking is disabled. This suite never disables it, so
     * the result is always a PendingCommand — narrowed here once instead of at
     * every call site.
     *
     * @param  array<string, mixed>  $parameters
     */
    private function artisanCommand(string $command, array $parameters = []): PendingCommand
    {
        $result = $this->artisan($command, $parameters);
        assert($result instanceof PendingCommand);

        return $result;
    }
}
