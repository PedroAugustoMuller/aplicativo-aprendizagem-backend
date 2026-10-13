<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Identity;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

final class ClassroomSubjectsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'app/Modules/Identity/Database/Migrations/2026_10_13_000001_classrooms_hold_several_subjects.php';

    public function test_each_classroom_keeps_its_subject_and_its_teachers_become_assignments_of_it(): void
    {
        $this->artisanCommand('migrate:rollback', ['--path' => self::MIGRATION])->assertSuccessful();

        $subjectId = (string) Str::uuid7();
        DB::table('subjects')->insert(['id' => $subjectId, 'name' => 'Química', 'created_at' => now(), 'updated_at' => now()]);
        $teacherId = $this->insertTeacher('b@escola.br');
        $classroomId = (string) Str::uuid7();
        DB::table('classrooms')->insert(['id' => $classroomId, 'name' => 'Química 1', 'subject_id' => $subjectId, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('classroom_teachers')->insert(['classroom_id' => $classroomId, 'user_id' => $teacherId]);

        $this->artisanCommand('migrate')->assertSuccessful();

        self::assertFalse(Schema::hasColumn('classrooms', 'subject_id'));
        self::assertSame([$subjectId], DB::table('classroom_subjects')->where('classroom_id', $classroomId)->pluck('subject_id')->all());
        $row = DB::table('classroom_teachers')->where('classroom_id', $classroomId)->first();
        self::assertNotNull($row);
        self::assertSame($subjectId, $row->subject_id);
        self::assertTrue(Str::isUuid((string) $row->id));
    }

    public function test_a_teacher_has_at_most_one_whole_classroom_assignment_per_classroom(): void
    {
        $classroomId = (string) Str::uuid7();
        DB::table('classrooms')->insert(['id' => $classroomId, 'name' => 'Turma', 'created_at' => now(), 'updated_at' => now()]);
        $teacherId = $this->insertTeacher('a@escola.br');
        DB::table('classroom_teachers')->insert(['id' => (string) Str::uuid7(), 'classroom_id' => $classroomId, 'user_id' => $teacherId, 'subject_id' => null]);

        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('classroom_teachers')->insert(['id' => (string) Str::uuid7(), 'classroom_id' => $classroomId, 'user_id' => $teacherId, 'subject_id' => null]);
    }

    private function insertTeacher(string $email): string
    {
        $id = (string) Str::uuid7();
        DB::table('users')->insert([
            'id' => $id, 'name' => 'Professor', 'role' => 'teacher', 'email' => $email,
            'password' => 'x', 'must_change_password' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    /** @param  array<string, string>  $parameters */
    private function artisanCommand(string $command, array $parameters = []): PendingCommand
    {
        $result = $this->artisan($command, $parameters);
        assert($result instanceof PendingCommand);

        return $result;
    }
}
