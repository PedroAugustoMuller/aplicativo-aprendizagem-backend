<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Identity;

use App\Shared\Infrastructure\Persistence\EloquentAttribute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Feature\Support\ActsAsUsers;
use Tests\TestCase;

final class TeacherAdminTest extends TestCase
{
    use ActsAsUsers;
    use RefreshDatabase;

    public function test_an_admin_creates_a_teacher_and_the_temporary_password_logs_them_in(): void
    {
        $admin = $this->tokenFor($this->makeUser('admin'));
        $id = (string) Str::uuid();

        $response = $this->withHeader('Authorization', 'Bearer '.$admin)
            ->postJson('/api/v1/teachers', ['id' => $id, 'name' => 'Ana', 'email' => 'ana@escola.br'])
            ->assertStatus(201)
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.name', 'Ana')
            ->assertJsonPath('data.login', 'ana@escola.br')
            ->assertJsonPath('data.role', 'teacher')
            ->assertJsonPath('data.must_change_password', true)
            ->assertJsonPath('data.active', true);

        $temporaryPassword = $response->json('data.temporary_password');
        self::assertIsString($temporaryPassword);
        self::assertSame(8, strlen($temporaryPassword));

        $this->app['auth']->forgetGuards();

        $this->postJson('/api/v1/auth/login', ['login' => 'ana@escola.br', 'password' => $temporaryPassword])
            ->assertOk()
            ->assertJsonPath('data.must_change_password', true);
    }

    public function test_replaying_the_same_creation_returns_the_same_temporary_password(): void
    {
        $admin = $this->tokenFor($this->makeUser('admin'));
        $id = (string) Str::uuid();
        $payload = ['id' => $id, 'name' => 'Ana', 'email' => 'ana@escola.br'];

        $first = $this->withHeader('Authorization', 'Bearer '.$admin)
            ->postJson('/api/v1/teachers', $payload)
            ->assertStatus(201);

        $second = $this->withHeader('Authorization', 'Bearer '.$admin)
            ->postJson('/api/v1/teachers', $payload)
            ->assertStatus(200);

        self::assertSame($first->json('data.temporary_password'), $second->json('data.temporary_password'));
    }

    public function test_creating_a_teacher_with_an_email_already_in_use_by_another_id_conflicts(): void
    {
        $admin = $this->tokenFor($this->makeUser('admin'));
        $this->withHeader('Authorization', 'Bearer '.$admin)
            ->postJson('/api/v1/teachers', ['id' => (string) Str::uuid(), 'name' => 'Ana', 'email' => 'ana@escola.br'])
            ->assertStatus(201);

        $this->withHeader('Authorization', 'Bearer '.$admin)
            ->postJson('/api/v1/teachers', ['id' => (string) Str::uuid(), 'name' => 'Outra Ana', 'email' => 'ana@escola.br'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'identity.email_already_taken')
            ->assertJsonPath('error.params.email', 'ana@escola.br');
    }

    public function test_resetting_the_password_twice_keeps_the_same_password_and_revokes_only_once(): void
    {
        $admin = $this->tokenFor($this->makeUser('admin'));
        $teacher = $this->makeUser('teacher', 'Ana', mustChange: false);
        $teacherId = EloquentAttribute::string($teacher->getKey(), 'users.id');
        $this->tokenFor($teacher);

        $first = $this->withHeader('Authorization', 'Bearer '.$admin)
            ->postJson('/api/v1/teachers/'.$teacherId.'/reset-password')
            ->assertOk();

        self::assertSame(0, PersonalAccessToken::query()->where('tokenable_id', $teacherId)->count());

        $newToken = $this->tokenFor($teacher);

        $second = $this->withHeader('Authorization', 'Bearer '.$admin)
            ->postJson('/api/v1/teachers/'.$teacherId.'/reset-password')
            ->assertOk();

        self::assertSame($first->json('data.temporary_password'), $second->json('data.temporary_password'));

        // The token minted after the FIRST reset must survive the second reset.
        self::assertSame(1, PersonalAccessToken::query()->where('tokenable_id', $teacherId)->count());

        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$newToken)
            ->getJson('/api/v1/auth/me')
            ->assertOk();
    }

    public function test_deactivating_a_teacher_revokes_their_tokens_and_blocks_login(): void
    {
        $admin = $this->tokenFor($this->makeUser('admin'));
        $teacher = $this->makeUser('teacher', 'Ana', mustChange: false);
        $teacherId = EloquentAttribute::string($teacher->getKey(), 'users.id');
        $teacherToken = $this->tokenFor($teacher);

        $this->withHeader('Authorization', 'Bearer '.$admin)
            ->postJson('/api/v1/teachers/'.$teacherId.'/deactivate')
            ->assertOk()
            ->assertJsonPath('data.active', false)
            ->assertJsonPath('data.temporary_password', null);

        self::assertSame(0, PersonalAccessToken::query()->where('tokenable_id', $teacherId)->count());

        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$teacherToken)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'auth.unauthenticated');

        $this->postJson('/api/v1/auth/login', ['login' => 'ana@escola.br', 'password' => 'password'])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'identity.invalid_credentials');
    }

    public function test_an_admin_cannot_deactivate_themself(): void
    {
        $admin = $this->makeUser('admin');
        $adminId = EloquentAttribute::string($admin->getKey(), 'users.id');
        $token = $this->tokenFor($admin);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/teachers/'.$adminId.'/deactivate')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'auth.forbidden');
    }

    public function test_an_admin_lists_staff_ordered_by_name_excluding_students(): void
    {
        $admin = $this->makeUser('admin', 'Carla');
        $adminToken = $this->tokenFor($admin);
        $teacherAna = $this->makeUser('teacher', 'Ana', mustChange: true);
        $teacherBeto = $this->makeUser('teacher', 'Beto', mustChange: false);
        $teacherDuda = $this->makeUser('teacher', 'Duda', mustChange: false);
        $this->makeUser('student', 'Zeca');

        $this->withHeader('Authorization', 'Bearer '.$adminToken)
            ->postJson('/api/v1/teachers/'.EloquentAttribute::string($teacherDuda->getKey(), 'users.id').'/deactivate')
            ->assertOk();

        $this->app['auth']->forgetGuards();

        $response = $this->withHeader('Authorization', 'Bearer '.$adminToken)
            ->getJson('/api/v1/teachers')
            ->assertOk();

        $response->assertJsonPath('data', [
            [
                'id' => EloquentAttribute::string($teacherAna->getKey(), 'users.id'),
                'name' => 'Ana',
                'login' => EloquentAttribute::string($teacherAna->getAttribute('email'), 'users.email'),
                'must_change_password' => true,
                'active' => true,
            ],
            [
                'id' => EloquentAttribute::string($teacherBeto->getKey(), 'users.id'),
                'name' => 'Beto',
                'login' => EloquentAttribute::string($teacherBeto->getAttribute('email'), 'users.email'),
                'must_change_password' => false,
                'active' => true,
            ],
            [
                'id' => EloquentAttribute::string($admin->getKey(), 'users.id'),
                'name' => 'Carla',
                'login' => EloquentAttribute::string($admin->getAttribute('email'), 'users.email'),
                'must_change_password' => false,
                'active' => true,
            ],
            [
                'id' => EloquentAttribute::string($teacherDuda->getKey(), 'users.id'),
                'name' => 'Duda',
                'login' => EloquentAttribute::string($teacherDuda->getAttribute('email'), 'users.email'),
                'must_change_password' => false,
                'active' => false,
            ],
        ]);
    }

    public function test_reactivating_a_teacher_restores_their_ability_to_log_in(): void
    {
        $admin = $this->tokenFor($this->makeUser('admin'));
        $teacher = $this->makeUser('teacher', 'Ana', mustChange: false);
        $teacherId = EloquentAttribute::string($teacher->getKey(), 'users.id');
        $login = EloquentAttribute::string($teacher->getAttribute('email'), 'users.email');

        $this->withHeader('Authorization', 'Bearer '.$admin)
            ->postJson('/api/v1/teachers/'.$teacherId.'/deactivate')
            ->assertOk()
            ->assertJsonPath('data.active', false);

        $this->postJson('/api/v1/auth/login', ['login' => $login, 'password' => 'password'])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'identity.invalid_credentials');

        $this->withHeader('Authorization', 'Bearer '.$admin)
            ->postJson('/api/v1/teachers/'.$teacherId.'/reactivate')
            ->assertOk()
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.temporary_password', null);

        $this->app['auth']->forgetGuards();

        $this->postJson('/api/v1/auth/login', ['login' => $login, 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.login', $login);
    }

    public function test_a_teacher_cannot_access_any_teacher_route(): void
    {
        $teacherToken = $this->tokenFor($this->makeUser('teacher'));

        $this->withHeader('Authorization', 'Bearer '.$teacherToken)
            ->getJson('/api/v1/teachers')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'auth.forbidden');

        $this->withHeader('Authorization', 'Bearer '.$teacherToken)
            ->postJson('/api/v1/teachers', ['id' => (string) Str::uuid(), 'name' => 'Ana', 'email' => 'ana@escola.br'])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'auth.forbidden');
    }
}
