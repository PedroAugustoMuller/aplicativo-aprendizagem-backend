<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Identity;

use App\Modules\Identity\Application\Port\CredentialVault;
use App\Modules\Identity\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\EloquentAttribute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Feature\Support\ActsAsUsers;
use Tests\TestCase;

final class ChangePasswordTest extends TestCase
{
    use ActsAsUsers;
    use RefreshDatabase;

    public function test_a_pending_student_can_change_their_password(): void
    {
        $user = $this->makeUser('student', mustChange: true);
        $token = $this->tokenFor($user);
        $this->app->make(CredentialVault::class)->store(new UserId(EloquentAttribute::string($user->getKey(), 'users.id')), 'password');

        $this->withToken($token)->putJson('/api/v1/auth/password', [
            'current_password' => 'password',
            'new_password' => 'brand-new-pass',
        ])->assertNoContent();

        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.must_change_password', false);

        self::assertSame(0, DB::table('pending_credentials')->where('user_id', $user->getKey())->count());
    }

    public function test_a_second_token_is_revoked_but_the_one_used_for_the_change_still_works(): void
    {
        $user = $this->makeUser('student', mustChange: true);
        $token = $this->tokenFor($user);
        $otherToken = $user->createToken('other', ['*'], now()->addDay())->plainTextToken;
        self::assertSame(2, PersonalAccessToken::query()->count());

        $this->withToken($token)->putJson('/api/v1/auth/password', [
            'current_password' => 'password',
            'new_password' => 'brand-new-pass',
        ])->assertNoContent();

        self::assertSame(1, PersonalAccessToken::query()->count());

        $this->app['auth']->forgetGuards();

        $this->withToken($otherToken)->getJson('/api/v1/auth/me')->assertStatus(401);

        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_a_voluntary_change_with_a_wrong_current_password_is_rejected(): void
    {
        $user = $this->makeUser('student');
        $token = $this->tokenFor($user);

        $this->withToken($token)->putJson('/api/v1/auth/password', [
            'current_password' => 'not-the-password',
            'new_password' => 'brand-new-pass',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'identity.current_password_invalid');

        self::assertTrue(Hash::check('password', EloquentAttribute::string($user->fresh()?->getAttribute('password'), 'users.password')));
    }

    public function test_a_pending_student_changes_without_sending_the_current_password(): void
    {
        $token = $this->tokenFor($this->makeUser('student', mustChange: true));

        $this->withToken($token)->putJson('/api/v1/auth/password', [
            'new_password' => 'brand-new-pass',
        ])->assertNoContent();

        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertJsonPath('data.must_change_password', false);
    }

    public function test_a_pending_student_s_wrong_current_password_is_ignored(): void
    {
        $token = $this->tokenFor($this->makeUser('student', mustChange: true));

        $this->withToken($token)->putJson('/api/v1/auth/password', [
            'current_password' => 'not-the-password',
            'new_password' => 'brand-new-pass',
        ])->assertNoContent();
    }

    public function test_a_voluntary_change_requires_the_current_password(): void
    {
        $token = $this->tokenFor($this->makeUser('student'));

        $this->withToken($token)->putJson('/api/v1/auth/password', [
            'new_password' => 'brand-new-pass',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation.failed')
            ->assertJsonPath('errors.current_password.0.code', 'validation.required');
    }

    public function test_a_new_password_shorter_than_eight_is_rejected(): void
    {
        $token = $this->tokenFor($this->makeUser('student', mustChange: true));

        $this->withToken($token)->putJson('/api/v1/auth/password', [
            'current_password' => 'password',
            'new_password' => 'short1',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation.failed')
            ->assertJsonPath('errors.new_password.0.code', 'validation.min');
    }

    public function test_a_new_password_equal_to_the_temporary_one_is_rejected(): void
    {
        $token = $this->tokenFor($this->makeUser('student', mustChange: true));

        $this->withToken($token)->putJson('/api/v1/auth/password', [
            'new_password' => 'password',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'identity.new_password_unchanged');

        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertJsonPath('data.must_change_password', true);
    }

    public function test_the_sixth_attempt_within_a_minute_is_throttled(): void
    {
        $token = $this->tokenFor($this->makeUser('student'));

        for ($i = 0; $i < 5; $i++) {
            $this->withToken($token)->putJson('/api/v1/auth/password', [
                'current_password' => 'wrong-password',
                'new_password' => 'brand-new-pass',
            ])->assertStatus(422);
        }

        $this->withToken($token)->putJson('/api/v1/auth/password', [
            'current_password' => 'wrong-password',
            'new_password' => 'brand-new-pass',
        ])
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'http.too_many_requests');
    }
}
