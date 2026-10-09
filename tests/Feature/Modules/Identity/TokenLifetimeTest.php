<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Identity;

use App\Modules\Identity\Database\Seeders\DevelopmentAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TokenLifetimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_login_token_lasts_thirty_days_and_not_a_minute_more(): void
    {
        $this->seed(DevelopmentAccountsSeeder::class);

        $token = $this->postJson('/api/v1/auth/login', ['login' => 'ana@escola.br', 'password' => 'password'])
            ->json('data.token');
        self::assertIsString($token);

        $this->travel(30 * 24 * 60 - 1)->minutes();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk();

        $this->app['auth']->forgetGuards();

        $this->travel(2)->minutes();
        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'auth.unauthenticated');
    }
}
