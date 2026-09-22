<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use App\Modules\Identity\Database\Seeders\DevelopmentAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_failed_logins_are_throttled_with_the_envelope(): void
    {
        $this->seed(DevelopmentAccountsSeeder::class);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', ['login' => 'ana@escola.br', 'password' => 'wrong'])
                ->assertStatus(401);
        }

        $this->postJson('/api/v1/auth/login', ['login' => 'ana@escola.br', 'password' => 'wrong'])
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'http.too_many_requests');
    }

    public function test_each_login_gets_its_own_throttle_bucket(): void
    {
        $this->seed(DevelopmentAccountsSeeder::class);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', ['login' => 'bia', 'password' => 'wrong'])
                ->assertStatus(401);
        }

        $this->postJson('/api/v1/auth/login', ['login' => 'bia', 'password' => 'wrong'])
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'http.too_many_requests');

        // A different login from the same IP has its own bucket: still 401, not 429.
        $this->postJson('/api/v1/auth/login', ['login' => 'caio', 'password' => 'wrong'])
            ->assertStatus(401);
    }

    public function test_many_different_accounts_from_the_same_ip_are_not_throttled_at_five(): void
    {
        $this->seed(DevelopmentAccountsSeeder::class);

        // Simulates a classroom: 30 distinct students on one shared IP (the
        // test client's fixed 127.0.0.1), each logging in with their own,
        // never-before-seen email. None of these shares a per-account bucket
        // with any other, so the per-account limiter (5/minute/account) never
        // fires for any single one of them.
        for ($student = 0; $student < 30; $student++) {
            $this->postJson('/api/v1/auth/login', [
                'login' => "student{$student}@escola.br",
                'password' => 'wrong',
            ])->assertStatus(401);
        }
    }

    public function test_the_api_group_is_actually_throttled(): void
    {
        $this->seed(DevelopmentAccountsSeeder::class);

        $token = $this->postJson('/api/v1/auth/login', [
            'login' => 'ana@escola.br',
            'password' => 'password',
        ])->json('data.token');

        self::assertIsString($token);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/subjects')
            ->assertOk()
            ->assertHeader('X-RateLimit-Limit', '120');
    }

    /**
     * Pins the fix for keying the `api` limiter on the bearer token instead of
     * $request->user() (which is always null there — see the comment in
     * AppServiceProvider::boot()). Under the old, IP-only fallback this test
     * would fail: Ana's requests alone would exhaust the shared 120/minute
     * bucket and Carla's very first request would already be 429, even though
     * they authenticate with two different tokens.
     */
    public function test_two_different_tokens_from_the_same_ip_get_independent_buckets(): void
    {
        $this->seed(DevelopmentAccountsSeeder::class);

        $anaToken = $this->postJson('/api/v1/auth/login', ['login' => 'ana@escola.br', 'password' => 'password'])
            ->json('data.token');
        $carlaToken = $this->postJson('/api/v1/auth/login', ['login' => 'carla.dias', 'password' => 'password'])
            ->json('data.token');

        self::assertIsString($anaToken);
        self::assertIsString($carlaToken);

        for ($request = 0; $request < 120; $request++) {
            $this->withHeader('Authorization', 'Bearer '.$anaToken)
                ->getJson('/api/v1/subjects')
                ->assertOk();
        }

        $this->withHeader('Authorization', 'Bearer '.$anaToken)
            ->getJson('/api/v1/subjects')
            ->assertStatus(429);

        // Carla shares Ana's IP (the test client's fixed 127.0.0.1) but carries a
        // different token, so her bucket is untouched by Ana's 120 requests.
        $this->withHeader('Authorization', 'Bearer '.$carlaToken)
            ->getJson('/api/v1/subjects')
            ->assertOk();
    }
}
