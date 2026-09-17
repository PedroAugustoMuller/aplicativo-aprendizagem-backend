<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Identity;

use App\Modules\Identity\Database\Seeders\TeacherUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

final class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TeacherUserSeeder::class);
    }

    public function test_it_returns_a_token_for_valid_credentials(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'ana@escola.br',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.email', 'ana@escola.br')
            ->assertJsonPath('data.name', 'Professora Ana')
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'token']]);

        self::assertNotEmpty($response->json('data.token'));
    }

    public function test_it_rejects_a_wrong_password_with_the_domain_code(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'ana@escola.br',
            'password' => 'wrong',
        ])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'identity.invalid_credentials');
    }

    public function test_an_unknown_email_is_indistinguishable_from_a_wrong_password(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@escola.br',
            'password' => 'password',
        ])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'identity.invalid_credentials')
            ->assertJsonPath('error.params', []);
    }

    public function test_missing_fields_produce_per_field_validation_codes(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation.failed')
            ->assertJsonPath('errors.email.0.code', 'validation.required')
            ->assertJsonPath('errors.password.0.code', 'validation.required');
    }

    public function test_the_token_grants_access_to_the_current_user(): void
    {
        $token = $this->postJson('/api/v1/auth/login', [
            'email' => 'ana@escola.br',
            'password' => 'password',
        ])->json('data.token');

        if (! is_string($token)) {
            self::fail('Expected the login response to include a string token.');
        }

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'ana@escola.br');
    }

    public function test_a_missing_token_returns_the_unauthenticated_envelope(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'auth.unauthenticated');
    }

    public function test_a_token_past_its_lifetime_is_rejected(): void
    {
        $token = $this->postJson('/api/v1/auth/login', [
            'email' => 'ana@escola.br',
            'password' => 'password',
        ])->json('data.token');

        if (! is_string($token)) {
            self::fail('Expected the login response to include a string token.');
        }

        // Sanity check: the token works right after issuance.
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertOk();

        $this->travel(8)->days();

        // See the comment in test_logout_revokes_the_token(): Laravel's TestCase
        // reuses one Application across the simulated requests in a single test
        // method, so the sanctum guard would otherwise still hold the user it
        // resolved before time travel. Clearing it here is a TEST concern, not
        // something to push into the controller.
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'auth.unauthenticated');
    }

    public function test_logout_revokes_the_token(): void
    {
        $token = $this->postJson('/api/v1/auth/login', [
            'email' => 'ana@escola.br',
            'password' => 'password',
        ])->json('data.token');

        if (! is_string($token)) {
            self::fail('Expected the login response to include a string token.');
        }

        self::assertSame(1, PersonalAccessToken::query()->count());

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout')
            ->assertNoContent();

        // Assert the row is gone. This is the revocation itself, and unlike the
        // request below it cannot be satisfied by a cache.
        self::assertSame(0, PersonalAccessToken::query()->count());

        // Laravel's TestCase reuses one Application across the simulated requests
        // in a single test method, so the sanctum guard still holds the user it
        // resolved during the logout call. Clearing it here is a TEST concern:
        // PHP-FPM tears the container down between real requests. Do NOT push
        // this into the controller — production is already correct without it,
        // and a per-action call would give false confidence about Octane while
        // leaving every other endpoint exposed. Octane readiness, if ever needed,
        // belongs in one central request-lifecycle listener.
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);
    }
}
