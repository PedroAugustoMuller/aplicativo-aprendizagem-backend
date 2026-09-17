<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use App\Modules\Identity\Database\Seeders\TeacherUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_failed_logins_are_throttled_with_the_envelope(): void
    {
        $this->seed(TeacherUserSeeder::class);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'ana@escola.br', 'password' => 'wrong'])
                ->assertStatus(401);
        }

        $this->postJson('/api/v1/auth/login', ['email' => 'ana@escola.br', 'password' => 'wrong'])
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'http.too_many_requests');
    }

    public function test_many_different_accounts_from_the_same_ip_are_not_throttled_at_five(): void
    {
        $this->seed(TeacherUserSeeder::class);

        // Simulates a classroom: 30 distinct students on one shared IP (the
        // test client's fixed 127.0.0.1), each logging in with their own,
        // never-before-seen email. None of these shares a per-account bucket
        // with any other, so the per-account limiter (5/minute/account) never
        // fires for any single one of them.
        for ($student = 0; $student < 30; $student++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => "student{$student}@escola.br",
                'password' => 'wrong',
            ])->assertStatus(401);
        }
    }

    public function test_the_api_group_is_actually_throttled(): void
    {
        $this->seed(TeacherUserSeeder::class);

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => 'ana@escola.br',
            'password' => 'password',
        ])->json('data.token');

        self::assertIsString($token);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/topics')
            ->assertOk()
            ->assertHeader('X-RateLimit-Limit', '120');
    }
}
