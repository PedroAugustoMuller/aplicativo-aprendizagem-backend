<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Identity;

use App\Modules\Identity\Infrastructure\Persistence\UserModel;
use App\Shared\Domain\Contract\TeachingAssignments;
use App\Shared\Infrastructure\Persistence\EloquentAttribute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Feature\Support\ActsAsUsers;
use Tests\TestCase;

final class EloquentTeachingAssignmentsTest extends TestCase
{
    use ActsAsUsers;
    use RefreshDatabase;

    public function test_only_the_active_classroom_subject_counts_for_a_teacher(): void
    {
        $teacher = $this->user('teacher');
        $chemistry = $this->subject('Química');
        $biology = $this->subject('Biologia');

        $activeClassroom = $this->classroom($chemistry);
        $inactiveClassroom = $this->classroom($biology, deactivated: true);

        DB::table('classroom_teachers')->insert([
            ['id' => (string) Str::uuid7(), 'classroom_id' => $activeClassroom, 'user_id' => $teacher->getKey(), 'subject_id' => $chemistry],
            ['id' => (string) Str::uuid7(), 'classroom_id' => $inactiveClassroom, 'user_id' => $teacher->getKey(), 'subject_id' => null],
        ]);

        $ids = $this->assignments()->subjectIdsTaughtBy(EloquentAttribute::string($teacher->getKey(), 'users.id'));

        self::assertSame([$chemistry], $ids);
    }

    public function test_a_student_enrolled_in_two_classrooms_of_the_same_subject_gets_the_id_once(): void
    {
        $student = $this->user('student');
        $chemistry = $this->subject('Química');

        $classroomA = $this->classroom($chemistry, name: 'Química 1');
        $classroomB = $this->classroom($chemistry, name: 'Química 2');

        DB::table('classroom_students')->insert([
            ['classroom_id' => $classroomA, 'user_id' => $student->getKey()],
            ['classroom_id' => $classroomB, 'user_id' => $student->getKey()],
        ]);

        $ids = $this->assignments()->subjectIdsEnrolledBy(EloquentAttribute::string($student->getKey(), 'users.id'));

        self::assertSame([$chemistry], $ids);
    }

    public function test_a_teacher_teaches_every_subject_of_a_whole_classroom_and_only_the_assigned_one_elsewhere(): void
    {
        $ana = $this->makeUser('teacher', 'Ana');
        $mat = $this->makeSubject('Matemática');
        $port = $this->makeSubject('Português');
        $hist = $this->makeSubject('História');
        $this->makeClassroomWith([$mat, $port], [[$ana, null]], name: '7º A');
        $this->makeClassroomWith([$port, $hist], [[$ana, $hist]], name: '8º A');
        $inactive = $this->makeClassroomWith([$hist, $this->makeSubject('Artes')], [[$ana, null]], name: '9º A');
        DB::table('classrooms')->where('id', $inactive)->update(['deactivated_at' => now()]);

        $taught = $this->assignments()->subjectIdsTaughtBy(EloquentAttribute::string($ana->getKey(), 'users.id'));

        self::assertEqualsCanonicalizing([$mat, $port, $hist], $taught);
    }

    public function test_a_student_is_enrolled_in_every_subject_of_their_active_classrooms(): void
    {
        $bia = $this->makeUser('student', 'Bia');
        $mat = $this->makeSubject('Matemática');
        $port = $this->makeSubject('Português');
        $this->makeClassroomWith([$mat, $port], students: [$bia], name: '7º A');

        self::assertEqualsCanonicalizing([$mat, $port], $this->assignments()->subjectIdsEnrolledBy(EloquentAttribute::string($bia->getKey(), 'users.id')));
    }

    private function assignments(): TeachingAssignments
    {
        return $this->app->make(TeachingAssignments::class);
    }

    private function user(string $role): UserModel
    {
        $id = (string) Str::uuid7();

        return UserModel::query()->create([
            'id' => $id,
            'name' => 'User '.$role,
            'role' => $role,
            'email' => $role === 'student' ? null : $id.'@escola.br',
            'username' => $role === 'student' ? 'u'.substr(str_replace('-', '', $id), 0, 12) : null,
            'password' => Hash::make('password'),
            'must_change_password' => false,
        ]);
    }

    private function subject(string $name): string
    {
        $id = (string) Str::uuid7();

        DB::table('subjects')->insert(['id' => $id, 'name' => $name, 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    private function classroom(string $subjectId, bool $deactivated = false, string $name = 'Turma'): string
    {
        $id = (string) Str::uuid7();

        DB::table('classrooms')->insert([
            'id' => $id,
            'name' => $name.' '.$id,
            'deactivated_at' => $deactivated ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('classroom_subjects')->insert(['classroom_id' => $id, 'subject_id' => $subjectId]);

        return $id;
    }
}
