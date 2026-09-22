<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Identity;

use App\Modules\Identity\Infrastructure\Persistence\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Feature\Support\ActsAsUsers;
use Tests\TestCase;

final class StudentRosterTest extends TestCase
{
    use ActsAsUsers;
    use RefreshDatabase;

    public function test_the_assigned_teacher_creates_two_students_with_the_same_name_and_they_can_log_in(): void
    {
        $subjectId = $this->makeSubject();
        $teacher = $this->makeUser('teacher');
        $classroomId = $this->makeClassroom($subjectId, teachers: [$teacher]);
        $teacherToken = $this->tokenFor($teacher);
        $id1 = (string) Str::uuid();
        $id2 = (string) Str::uuid();

        $response = $this->withHeader('Authorization', 'Bearer '.$teacherToken)
            ->postJson('/api/v1/classrooms/'.$classroomId.'/students', [
                'students' => [
                    ['id' => $id1, 'name' => 'Ana Souza'],
                    ['id' => $id2, 'name' => 'Ana Souza'],
                ],
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.0.login', 'ana.souza')
            ->assertJsonPath('data.1.login', 'ana.souza2')
            ->assertJsonPath('data.0.must_change_password', true)
            ->assertJsonPath('data.1.must_change_password', true);

        $password1 = $response->json('data.0.temporary_password');
        self::assertIsString($password1);

        $this->app['auth']->forgetGuards();

        $this->postJson('/api/v1/auth/login', ['login' => 'ana.souza', 'password' => $password1])
            ->assertOk()
            ->assertJsonPath('data.must_change_password', true);
    }

    public function test_replaying_the_same_batch_returns_the_same_data(): void
    {
        $subjectId = $this->makeSubject();
        $teacher = $this->makeUser('teacher');
        $classroomId = $this->makeClassroom($subjectId, teachers: [$teacher]);
        $teacherToken = $this->tokenFor($teacher);
        $payload = ['students' => [['id' => (string) Str::uuid(), 'name' => 'Ana Souza']]];

        $first = $this->withHeader('Authorization', 'Bearer '.$teacherToken)
            ->postJson('/api/v1/classrooms/'.$classroomId.'/students', $payload)
            ->assertStatus(201);

        $second = $this->withHeader('Authorization', 'Bearer '.$teacherToken)
            ->postJson('/api/v1/classrooms/'.$classroomId.'/students', $payload)
            ->assertStatus(200);

        self::assertSame($first->json('data'), $second->json('data'));
    }

    public function test_more_than_fifty_rows_is_rejected(): void
    {
        $subjectId = $this->makeSubject();
        $teacher = $this->makeUser('teacher');
        $classroomId = $this->makeClassroom($subjectId, teachers: [$teacher]);
        $teacherToken = $this->tokenFor($teacher);

        $students = [];
        for ($i = 0; $i < 51; $i++) {
            $students[] = ['id' => (string) Str::uuid(), 'name' => 'Aluno '.$i];
        }

        $this->withHeader('Authorization', 'Bearer '.$teacherToken)
            ->postJson('/api/v1/classrooms/'.$classroomId.'/students', ['students' => $students])
            ->assertStatus(422)
            ->assertJsonPath('errors.students.0.code', 'validation.max');
    }

    public function test_duplicate_ids_in_one_body_are_rejected(): void
    {
        $subjectId = $this->makeSubject();
        $teacher = $this->makeUser('teacher');
        $classroomId = $this->makeClassroom($subjectId, teachers: [$teacher]);
        $teacherToken = $this->tokenFor($teacher);
        $id = (string) Str::uuid();

        $response = $this->withHeader('Authorization', 'Bearer '.$teacherToken)
            ->postJson('/api/v1/classrooms/'.$classroomId.'/students', [
                'students' => [
                    ['id' => $id, 'name' => 'Ana Souza'],
                    ['id' => $id, 'name' => 'Beto Lima'],
                ],
            ])
            ->assertStatus(422);

        // Keys in `errors` are literal dotted attribute paths (e.g. "students.0.id"),
        // not nested arrays — dot-notation lookup via json('errors.students.0.id')
        // would misread that dot as nesting, so the raw array is inspected instead.
        /** @var array<string, list<array{code: string, params: object}>> $errors */
        $errors = $response->json('errors') ?? [];
        $codes = [];
        foreach ($errors as $failures) {
            foreach ($failures as $failure) {
                $codes[] = $failure['code'];
            }
        }
        self::assertContains('validation.distinct', $codes);
    }

    public function test_a_conflicting_row_persists_no_new_user(): void
    {
        $subjectId = $this->makeSubject();
        $teacher = $this->makeUser('teacher');
        $classroomId = $this->makeClassroom($subjectId, teachers: [$teacher]);
        $teacherToken = $this->tokenFor($teacher);
        $existingId = (string) Str::uuid();

        $this->withHeader('Authorization', 'Bearer '.$teacherToken)
            ->postJson('/api/v1/classrooms/'.$classroomId.'/students', [
                'students' => [['id' => $existingId, 'name' => 'Ana Souza']],
            ])
            ->assertStatus(201);

        $countBefore = UserModel::query()->count();
        $newId = (string) Str::uuid();

        $this->withHeader('Authorization', 'Bearer '.$teacherToken)
            ->postJson('/api/v1/classrooms/'.$classroomId.'/students', [
                'students' => [
                    ['id' => $existingId, 'name' => 'Outro Nome'],
                    ['id' => $newId, 'name' => 'Beto Lima'],
                ],
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'system.idempotency_conflict');

        self::assertSame($countBefore, UserModel::query()->count());
    }

    public function test_an_unrelated_teacher_and_a_student_are_denied(): void
    {
        $subjectId = $this->makeSubject();
        $teacher = $this->makeUser('teacher');
        $classroomId = $this->makeClassroom($subjectId, teachers: [$teacher]);
        $unrelatedTeacherToken = $this->tokenFor($this->makeUser('teacher'));
        $studentToken = $this->tokenFor($this->makeUser('student'));
        $payload = ['students' => [['id' => (string) Str::uuid(), 'name' => 'Ana Souza']]];

        $this->withHeader('Authorization', 'Bearer '.$unrelatedTeacherToken)
            ->postJson('/api/v1/classrooms/'.$classroomId.'/students', $payload)
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'auth.forbidden');

        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$studentToken)
            ->postJson('/api/v1/classrooms/'.$classroomId.'/students', $payload)
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'auth.forbidden');
    }

    public function test_a_student_moved_between_classrooms_can_have_their_password_reset_by_either_teacher(): void
    {
        $subjectId = $this->makeSubject();
        $teacherA = $this->makeUser('teacher');
        $teacherB = $this->makeUser('teacher');
        $classroomA = $this->makeClassroom($subjectId, teachers: [$teacherA], name: 'Química 1');
        $classroomB = $this->makeClassroom($subjectId, teachers: [$teacherB], name: 'Química 2');
        $tokenA = $this->tokenFor($teacherA);
        $tokenB = $this->tokenFor($teacherB);
        $studentId = (string) Str::uuid();

        $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->postJson('/api/v1/classrooms/'.$classroomA.'/students', [
                'students' => [['id' => $studentId, 'name' => 'Ana Souza']],
            ])
            ->assertStatus(201);

        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$tokenB)
            ->putJson('/api/v1/classrooms/'.$classroomB.'/students/'.$studentId)
            ->assertOk()
            ->assertJsonPath('data.student_count', 1);

        $this->withHeader('Authorization', 'Bearer '.$tokenB)
            ->postJson('/api/v1/students/'.$studentId.'/reset-password')
            ->assertOk();

        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->postJson('/api/v1/students/'.$studentId.'/reset-password')
            ->assertOk();
    }

    public function test_reset_twice_deactivate_and_reactivate_a_student(): void
    {
        $subjectId = $this->makeSubject();
        $teacher = $this->makeUser('teacher');
        $classroomId = $this->makeClassroom($subjectId, teachers: [$teacher]);
        $teacherToken = $this->tokenFor($teacher);
        $studentId = (string) Str::uuid();

        $created = $this->withHeader('Authorization', 'Bearer '.$teacherToken)
            ->postJson('/api/v1/classrooms/'.$classroomId.'/students', [
                'students' => [['id' => $studentId, 'name' => 'Ana Souza']],
            ])
            ->assertStatus(201);
        $login = $created->json('data.0.login');
        self::assertIsString($login);

        $first = $this->withHeader('Authorization', 'Bearer '.$teacherToken)
            ->postJson('/api/v1/students/'.$studentId.'/reset-password')
            ->assertOk();

        $second = $this->withHeader('Authorization', 'Bearer '.$teacherToken)
            ->postJson('/api/v1/students/'.$studentId.'/reset-password')
            ->assertOk();

        self::assertSame($first->json('data.temporary_password'), $second->json('data.temporary_password'));
        $password = $second->json('data.temporary_password');

        $this->withHeader('Authorization', 'Bearer '.$teacherToken)
            ->postJson('/api/v1/students/'.$studentId.'/deactivate')
            ->assertOk()
            ->assertJsonPath('data.active', false);

        self::assertSame(0, PersonalAccessToken::query()->where('tokenable_id', $studentId)->count());

        $this->app['auth']->forgetGuards();

        $this->postJson('/api/v1/auth/login', ['login' => $login, 'password' => $password])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'identity.invalid_credentials');

        $this->withHeader('Authorization', 'Bearer '.$teacherToken)
            ->postJson('/api/v1/students/'.$studentId.'/reactivate')
            ->assertOk()
            ->assertJsonPath('data.active', true);

        $this->postJson('/api/v1/auth/login', ['login' => $login, 'password' => $password])
            ->assertOk()
            ->assertJsonPath('data.login', $login);
    }

    public function test_the_assigned_teacher_lists_students_without_exposing_passwords(): void
    {
        $subjectId = $this->makeSubject();
        $teacher = $this->makeUser('teacher');
        $classroomId = $this->makeClassroom($subjectId, teachers: [$teacher]);
        $teacherToken = $this->tokenFor($teacher);

        $created = $this->withHeader('Authorization', 'Bearer '.$teacherToken)
            ->postJson('/api/v1/classrooms/'.$classroomId.'/students', [
                'students' => [
                    ['id' => (string) Str::uuid(), 'name' => 'Beto Lima'],
                    ['id' => (string) Str::uuid(), 'name' => 'Ana Souza'],
                ],
            ])
            ->assertStatus(201);

        $response = $this->withHeader('Authorization', 'Bearer '.$teacherToken)
            ->getJson('/api/v1/classrooms/'.$classroomId.'/students')
            ->assertOk();

        $response->assertJsonPath('data', [
            [
                'id' => $created->json('data.1.id'),
                'name' => 'Ana Souza',
                'login' => 'ana.souza',
                'must_change_password' => true,
                'active' => true,
            ],
            [
                'id' => $created->json('data.0.id'),
                'name' => 'Beto Lima',
                'login' => 'beto.lima',
                'must_change_password' => true,
                'active' => true,
            ],
        ]);

        self::assertStringNotContainsString('temporary_password', $response->getContent() ?: '');
    }
}
