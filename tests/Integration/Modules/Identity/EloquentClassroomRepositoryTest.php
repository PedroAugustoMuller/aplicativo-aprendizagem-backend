<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Identity;

use App\Modules\Identity\Domain\Entity\Classroom;
use App\Modules\Identity\Domain\Repository\ClassroomRepository;
use App\Modules\Identity\Domain\ValueObject\UserId;
use App\Modules\Identity\Infrastructure\Persistence\UserModel;
use App\Shared\Infrastructure\Persistence\EloquentAttribute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

final class EloquentClassroomRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_finds_every_classroom_a_student_is_enrolled_in_with_their_teachers_and_students(): void
    {
        $student = $this->user('student');
        $otherStudent = $this->user('student');
        $teacherA = $this->user('teacher');
        $teacherB = $this->user('teacher');
        $subject = $this->subject();

        $classroomA = $this->classroom($subject, 'Química 1');
        $classroomB = $this->classroom($subject, 'Química 2');
        $unrelated = $this->classroom($subject, 'Química 3');

        DB::table('classroom_teachers')->insert([
            ['classroom_id' => $classroomA, 'user_id' => $teacherA->getKey()],
            ['classroom_id' => $classroomB, 'user_id' => $teacherB->getKey()],
        ]);
        DB::table('classroom_students')->insert([
            ['classroom_id' => $classroomA, 'user_id' => $student->getKey()],
            ['classroom_id' => $classroomA, 'user_id' => $otherStudent->getKey()],
            ['classroom_id' => $classroomB, 'user_id' => $student->getKey()],
            ['classroom_id' => $unrelated, 'user_id' => $otherStudent->getKey()],
        ]);

        $found = $this->repository()->findByStudent(new UserId($this->id($student)));

        self::assertCount(2, $found);
        $ids = array_map(fn (Classroom $c): string => $c->id()->value(), $found);
        self::assertContains($classroomA, $ids);
        self::assertContains($classroomB, $ids);

        $index = array_search($classroomA, $ids, true);
        self::assertIsInt($index);
        $withA = $found[$index];
        self::assertTrue($withA->isTaughtBy(new UserId($this->id($teacherA))));
        self::assertTrue($withA->hasStudent(new UserId($this->id($student))));
        self::assertTrue($withA->hasStudent(new UserId($this->id($otherStudent))));
    }

    public function test_it_returns_an_empty_list_for_a_student_in_no_classroom(): void
    {
        $student = $this->user('student');

        self::assertSame([], $this->repository()->findByStudent(new UserId($this->id($student))));
    }

    /** Bounded query count: no per-classroom round trip regardless of how many classrooms match. */
    public function test_it_does_not_query_once_per_classroom(): void
    {
        $student = $this->user('student');
        $subject = $this->subject();

        $classroomIds = [];
        for ($i = 0; $i < 5; $i++) {
            $classroomIds[] = $this->classroom($subject, 'Química '.$i);
        }
        DB::table('classroom_students')->insert(array_map(
            static fn (string $id): array => ['classroom_id' => $id, 'user_id' => $student->getKey()],
            $classroomIds,
        ));

        DB::enableQueryLog();
        $found = $this->repository()->findByStudent(new UserId($this->id($student)));
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        self::assertCount(5, $found);
        self::assertLessThanOrEqual(3, $queryCount);
    }

    private function repository(): ClassroomRepository
    {
        return $this->app->make(ClassroomRepository::class);
    }

    private function id(UserModel $user): string
    {
        return EloquentAttribute::string($user->getKey(), 'users.id');
    }

    private function user(string $role): UserModel
    {
        $id = (string) Str::uuid7();

        return UserModel::query()->create([
            'id' => $id,
            'name' => 'User '.$id,
            'role' => $role,
            'email' => $role === 'student' ? null : $id.'@escola.br',
            'username' => $role === 'student' ? 'u'.substr(str_replace('-', '', $id), 0, 12) : null,
            'password' => Hash::make('password'),
            'must_change_password' => false,
        ]);
    }

    private function subject(): string
    {
        $id = (string) Str::uuid7();
        DB::table('subjects')->insert(['id' => $id, 'name' => 'Química', 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    private function classroom(string $subjectId, string $name): string
    {
        $id = (string) Str::uuid7();
        DB::table('classrooms')->insert([
            'id' => $id,
            'name' => $name,
            'subject_id' => $subjectId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
}
