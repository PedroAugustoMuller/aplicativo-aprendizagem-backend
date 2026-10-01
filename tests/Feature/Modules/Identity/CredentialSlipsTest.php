<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Identity;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\Support\ActsAsUsers;
use Tests\TestCase;

final class CredentialSlipsTest extends TestCase
{
    use ActsAsUsers;
    use RefreshDatabase;

    public function test_it_lists_slips_for_students_still_pending_a_password_change(): void
    {
        $subjectId = $this->makeSubject();
        $teacher = $this->makeUser('teacher');
        $classroomId = $this->makeClassroom($subjectId, teachers: [$teacher]);
        $teacherToken = $this->tokenFor($teacher);

        $created = $this->withToken($teacherToken)
            ->postJson('/api/v1/classrooms/'.$classroomId.'/students', [
                'students' => [
                    ['id' => (string) Str::uuid(), 'name' => 'Ana Souza'],
                    ['id' => (string) Str::uuid(), 'name' => 'Beto Lima'],
                    ['id' => (string) Str::uuid(), 'name' => 'Caio Nunes'],
                ],
            ])
            ->assertStatus(201);

        $anaLogin = $created->json('data.0.login');
        $anaPassword = $created->json('data.0.temporary_password');
        self::assertIsString($anaLogin);
        self::assertIsString($anaPassword);

        $this->app['auth']->forgetGuards();

        $anaToken = $this->postJson('/api/v1/auth/login', ['login' => $anaLogin, 'password' => $anaPassword])
            ->assertOk()
            ->json('data.token');
        self::assertIsString($anaToken);

        $this->app['auth']->forgetGuards();

        $this->withToken($anaToken)->putJson('/api/v1/auth/password', [
            'current_password' => $anaPassword,
            'new_password' => 'brand-new-pass',
        ])->assertNoContent();

        $this->app['auth']->forgetGuards();

        $response = $this->withToken($teacherToken)
            ->getJson('/api/v1/classrooms/'.$classroomId.'/credentials')
            ->assertOk();

        $response->assertJsonPath('data', [
            [
                'user_id' => $created->json('data.1.id'),
                'name' => 'Beto Lima',
                'login' => $created->json('data.1.login'),
                'temporary_password' => $created->json('data.1.temporary_password'),
            ],
            [
                'user_id' => $created->json('data.2.id'),
                'name' => 'Caio Nunes',
                'login' => $created->json('data.2.login'),
                'temporary_password' => $created->json('data.2.temporary_password'),
            ],
        ]);

        self::assertStringContainsString('no-store', $response->headers->get('Cache-Control') ?? '');
    }

    public function test_an_unrelated_teacher_is_denied(): void
    {
        $subjectId = $this->makeSubject();
        $teacher = $this->makeUser('teacher');
        $classroomId = $this->makeClassroom($subjectId, teachers: [$teacher]);
        $unrelatedTeacherToken = $this->tokenFor($this->makeUser('teacher'));

        $this->withToken($unrelatedTeacherToken)
            ->getJson('/api/v1/classrooms/'.$classroomId.'/credentials')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'auth.forbidden');
    }
}
