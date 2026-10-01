<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Identity;

use App\Modules\Identity\Domain\Entity\Classroom;
use App\Modules\Identity\Domain\Entity\User;
use App\Modules\Identity\Domain\Repository\ClassroomRepository;
use App\Modules\Identity\Domain\Repository\UserRepository;
use App\Modules\Identity\Domain\ValueObject\ClassroomId;
use App\Modules\Identity\Domain\ValueObject\ClassroomName;
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

    /*
     * Two requests that load the same classroom before either saves (two
     * teachers adding students at once, a teacher enrolling while the admin
     * renames) must both land. save() writes what changed, not a snapshot.
     */
    public function test_concurrent_enrolments_in_one_classroom_are_both_kept(): void
    {
        $classroomId = $this->classroom($this->subject(), 'Química 1');
        $ana = $this->user('student');
        $bia = $this->user('student');

        $first = $this->load($classroomId);
        $second = $this->load($classroomId);
        $first->enrol($this->domainUser($ana));
        $second->enrol($this->domainUser($bia));
        $this->repository()->save($first);
        $this->repository()->save($second);

        $stored = $this->load($classroomId);
        self::assertTrue($stored->hasStudent(new UserId($this->id($ana))));
        self::assertTrue($stored->hasStudent(new UserId($this->id($bia))));
    }

    public function test_an_enrolment_does_not_undo_a_concurrent_unenrolment(): void
    {
        $classroomId = $this->classroom($this->subject(), 'Química 1');
        $ana = $this->user('student');
        $caio = $this->user('student');
        DB::table('classroom_students')->insert(['classroom_id' => $classroomId, 'user_id' => $ana->getKey()]);

        $first = $this->load($classroomId);
        $second = $this->load($classroomId);
        $first->unenrol(new UserId($this->id($ana)));
        $second->enrol($this->domainUser($caio));
        $this->repository()->save($first);
        $this->repository()->save($second);

        $stored = $this->load($classroomId);
        self::assertFalse($stored->hasStudent(new UserId($this->id($ana))));
        self::assertTrue($stored->hasStudent(new UserId($this->id($caio))));
    }

    public function test_an_enrolment_does_not_undo_a_concurrent_rename_or_teacher_assignment(): void
    {
        $classroomId = $this->classroom($this->subject(), 'Química 1');
        $teacher = $this->user('teacher');
        $ana = $this->user('student');

        $renamed = $this->load($classroomId);
        $staffed = $this->load($classroomId);
        $enrolled = $this->load($classroomId);
        $renamed->rename(new ClassroomName('Química A'));
        $staffed->assignTeachers([$this->domainUser($teacher)]);
        $enrolled->enrol($this->domainUser($ana));
        $this->repository()->save($renamed);
        $this->repository()->save($staffed);
        $this->repository()->save($enrolled);

        $stored = $this->load($classroomId);
        self::assertSame('Química A', $stored->name()->value());
        self::assertTrue($stored->isTaughtBy(new UserId($this->id($teacher))));
        self::assertTrue($stored->hasStudent(new UserId($this->id($ana))));
    }

    private function load(string $classroomId): Classroom
    {
        $classroom = $this->repository()->findById(new ClassroomId($classroomId));
        self::assertNotNull($classroom);

        return $classroom;
    }

    private function domainUser(UserModel $model): User
    {
        $user = $this->app->make(UserRepository::class)->findById(new UserId($this->id($model)));
        self::assertNotNull($user);

        return $user;
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
