<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Identity\Database\Seeders\DevelopmentAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class DevelopmentSeedTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Guards against the dev dataset (a live admin login, ana@escola.br /
     * password) leaking into a production `db:seed --force`, which would also
     * permanently block `identity:create-admin` (it refuses to run once any
     * admin exists).
     */
    public function test_it_does_not_seed_outside_local_or_testing(): void
    {
        $original = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            // --force, not $this->seed(): db:seed's own ConfirmableTrait would
            // otherwise stop to ask for confirmation now that the app reports
            // itself as running in production, which is a different guard than
            // the one this test exercises.
            $this->artisan('db:seed', ['--class' => DevelopmentAccountsSeeder::class, '--force' => true]);
        } finally {
            $this->app['env'] = $original;
        }

        self::assertSame(0, DB::table('users')->count());
    }

    public function test_reseeding_twice_leaves_the_dataset_at_the_same_size(): void
    {
        $this->seed();
        $this->seed();

        self::assertSame(4, DB::table('users')->count());
        self::assertSame(2, DB::table('subjects')->count());
        self::assertSame(6, DB::table('topics')->count());
        self::assertSame(1, DB::table('classrooms')->count());
        self::assertSame(1, DB::table('pending_credentials')->count());
    }

    public function test_diego_logs_in_with_the_reissued_temporary_password_and_must_change_it(): void
    {
        $this->seed();
        $this->seed();

        $this->postJson('/api/v1/auth/login', ['login' => 'diego.souza', 'password' => 'Temp2345'])
            ->assertOk()
            ->assertJsonPath('data.must_change_password', true);
    }

    public function test_carla_sees_chemistry_through_her_classroom_but_not_biology(): void
    {
        $this->seed();

        $token = $this->tokenFor('carla.dias', 'password');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/v1/subjects');

        $response->assertOk();

        /** @var list<array{name: string}> $data */
        $data = $response->json('data');
        $names = array_column($data, 'name');

        self::assertContains('Química', $names);
        self::assertNotContains('Biologia', $names);
    }

    public function test_bruno_sees_chemistry_1_in_his_classrooms(): void
    {
        $this->seed();

        $token = $this->tokenFor('bruno@escola.br', 'password');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/v1/classrooms');

        $response->assertOk();

        /** @var list<array{name: string}> $data */
        $data = $response->json('data');
        $names = array_column($data, 'name');

        self::assertContains('Química 1', $names);
    }

    private function tokenFor(string $login, string $password): string
    {
        $token = $this->postJson('/api/v1/auth/login', ['login' => $login, 'password' => $password])->json('data.token');

        if (! is_string($token)) {
            self::fail('Expected the login response to include a string token.');
        }

        return $token;
    }
}
