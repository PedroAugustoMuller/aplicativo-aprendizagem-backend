<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Identity;

use App\Modules\Identity\Database\Seeders\DevelopmentAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

final class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_first_admin_and_prints_a_temporary_password(): void
    {
        $this->runArtisan('identity:create-admin', ['name' => 'Direção', 'email' => 'direcao@escola.br'])
            ->expectsOutputToContain('Temporary password:')
            ->assertSuccessful();

        $row = DB::table('users')->where('email', 'direcao@escola.br')->first();
        self::assertNotNull($row);
        self::assertSame('admin', $row->role);
        self::assertTrue((bool) $row->must_change_password);
        self::assertSame(1, DB::table('pending_credentials')->count());
    }

    public function test_it_refuses_when_an_admin_exists(): void
    {
        $this->seed(DevelopmentAccountsSeeder::class);

        $this->runArtisan('identity:create-admin', ['name' => 'X', 'email' => 'x@escola.br'])
            ->expectsOutputToContain('An admin already exists.')
            ->assertFailed();
    }

    public function test_it_rejects_a_malformed_email(): void
    {
        $this->runArtisan('identity:create-admin', ['name' => 'X', 'email' => 'nope'])
            ->expectsOutputToContain('Invalid email.')
            ->assertFailed();
    }

    /**
     * InteractsWithConsole::artisan() is declared to return PendingCommand|int
     * because it falls back to the real exit code when console-output mocking
     * is disabled. This suite never disables it, so the result here is always
     * a PendingCommand; assert() lets PHPStan narrow the type instead of
     * weakening it or suppressing the mismatch.
     *
     * @param  array<string, mixed>  $parameters
     */
    private function runArtisan(string $command, array $parameters = []): PendingCommand
    {
        $pending = $this->artisan($command, $parameters);

        assert($pending instanceof PendingCommand);

        return $pending;
    }
}
