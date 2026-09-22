<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Content;

use App\Modules\Identity\Database\Seeders\DevelopmentAccountsSeeder;
use App\Modules\Identity\Domain\ValueObject\UserId;
use App\Modules\Identity\Infrastructure\Persistence\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

final class SubjectAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DevelopmentAccountsSeeder::class);
    }

    public function test_an_admin_creates_a_subject(): void
    {
        $id = (string) Str::uuid();

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->postJson('/api/v1/subjects', ['id' => $id, 'name' => 'Biologia'])
            ->assertStatus(201)
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.name', 'Biologia')
            ->assertJsonPath('data.active', true);
    }

    public function test_replaying_the_same_body_returns_the_original_with_200(): void
    {
        $id = (string) Str::uuid();
        $token = $this->adminToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/subjects', ['id' => $id, 'name' => 'Biologia'])
            ->assertStatus(201);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/subjects', ['id' => $id, 'name' => 'Biologia'])
            ->assertStatus(200)
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.name', 'Biologia');
    }

    public function test_the_same_id_with_a_different_name_is_an_idempotency_conflict(): void
    {
        $id = (string) Str::uuid();
        $token = $this->adminToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/subjects', ['id' => $id, 'name' => 'Biologia'])
            ->assertStatus(201);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/subjects', ['id' => $id, 'name' => 'Física'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'system.idempotency_conflict');
    }

    public function test_a_duplicate_name_with_a_new_id_is_taken_case_insensitively(): void
    {
        $token = $this->adminToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/subjects', ['id' => (string) Str::uuid(), 'name' => 'Biologia'])
            ->assertStatus(201);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/subjects', ['id' => (string) Str::uuid(), 'name' => 'biologia'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'content.subject.name_already_taken')
            ->assertJsonPath('error.params.name', 'biologia');
    }

    public function test_a_teacher_cannot_create_a_subject(): void
    {
        $token = $this->teacherToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/subjects', ['id' => (string) Str::uuid(), 'name' => 'Biologia'])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'auth.forbidden');
    }

    public function test_an_admin_renames_a_subject(): void
    {
        $id = (string) Str::uuid();
        $token = $this->adminToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/subjects', ['id' => $id, 'name' => 'Biologia'])
            ->assertStatus(201);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/subjects/'.$id, ['name' => 'Biologia Avançada'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Biologia Avançada');
    }

    public function test_renaming_an_unknown_subject_is_not_found(): void
    {
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->patchJson('/api/v1/subjects/'.(string) Str::uuid(), ['name' => 'Biologia'])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'content.subject_not_found');
    }

    public function test_an_admin_deactivates_a_subject_and_the_list_reflects_it(): void
    {
        $id = (string) Str::uuid();
        $token = $this->adminToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/subjects', ['id' => $id, 'name' => 'Biologia'])
            ->assertStatus(201);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/subjects/'.$id.'/deactivate')
            ->assertOk()
            ->assertJsonPath('data.active', false);

        $list = $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/v1/subjects');

        $list->assertOk();
        $data = $list->json('data');
        self::assertIsArray($data);

        $item = null;

        foreach ($data as $row) {
            if (is_array($row) && ($row['id'] ?? null) === $id) {
                $item = $row;
            }
        }

        self::assertIsArray($item);
        self::assertFalse($item['active']);
    }

    public function test_a_non_uuid_id_is_rejected_by_validation(): void
    {
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->postJson('/api/v1/subjects', ['id' => 'not-a-uuid', 'name' => 'Biologia'])
            ->assertStatus(422)
            ->assertJsonPath('errors.id.0.code', 'validation.uuid');
    }

    private function adminToken(): string
    {
        $token = $this->postJson('/api/v1/auth/login', [
            'login' => 'ana@escola.br',
            'password' => 'password',
        ])->json('data.token');

        if (! is_string($token)) {
            self::fail('Expected the login response to include a string token.');
        }

        return $token;
    }

    private function teacherToken(): string
    {
        $id = UserId::random()->value();
        $email = $id.'@escola.br';

        UserModel::query()->create([
            'id' => $id,
            'name' => 'Professor Teste',
            'role' => 'teacher',
            'email' => $email,
            'password' => Hash::make('password'),
            'must_change_password' => false,
        ]);

        $token = $this->postJson('/api/v1/auth/login', [
            'login' => $email,
            'password' => 'password',
        ])->json('data.token');

        if (! is_string($token)) {
            self::fail('Expected the login response to include a string token.');
        }

        return $token;
    }
}
