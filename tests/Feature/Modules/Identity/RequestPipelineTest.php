<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Identity;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Feature\Support\ActsAsUsers;
use Tests\TestCase;

final class RequestPipelineTest extends TestCase
{
    use ActsAsUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['api', 'auth:sanctum', 'account.active', 'password.changed'])->prefix('api/v1/_probe')->group(function (): void {
            Route::get('/any', fn () => ['ok' => true]);
            Route::get('/staff', fn () => ['ok' => true])->middleware('role:staff');
            Route::get('/admin', fn () => ['ok' => true])->middleware('role:admin');
        });

        $this->app['router']->getRoutes()->refreshNameLookups();
    }

    public function test_a_deactivated_account_is_rejected_and_its_token_revoked(): void
    {
        $user = $this->makeUser('teacher');
        $token = $this->tokenFor($user);
        $user->forceFill(['deactivated_at' => now()])->save();

        $this->withToken($token)->getJson('/api/v1/_probe/any')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'identity.account_deactivated');

        self::assertSame(0, PersonalAccessToken::query()->count());
    }

    public function test_a_pending_password_change_blocks_everything_but_the_three_routes(): void
    {
        $token = $this->tokenFor($this->makeUser('student', mustChange: true));

        $this->withToken($token)->getJson('/api/v1/_probe/any')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'identity.password_change_required');

        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_role_admin_rejects_a_teacher_with_forbidden(): void
    {
        $token = $this->tokenFor($this->makeUser('teacher'));

        $this->withToken($token)->getJson('/api/v1/_probe/admin')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'auth.forbidden');
    }

    public function test_role_staff_admits_admin_and_teacher_but_not_student(): void
    {
        $admin = $this->tokenFor($this->makeUser('admin'));
        $teacher = $this->tokenFor($this->makeUser('teacher'));
        $student = $this->tokenFor($this->makeUser('student'));

        $this->withToken($admin)->getJson('/api/v1/_probe/staff')->assertOk();

        $this->app['auth']->forgetGuards();

        $this->withToken($teacher)->getJson('/api/v1/_probe/staff')->assertOk();

        $this->app['auth']->forgetGuards();

        $this->withToken($student)->getJson('/api/v1/_probe/staff')->assertStatus(403);
    }
}
