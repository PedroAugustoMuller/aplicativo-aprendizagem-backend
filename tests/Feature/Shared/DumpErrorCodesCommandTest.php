<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

final class DumpErrorCodesCommandTest extends TestCase
{
    private string $path;

    private string $originalContents;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = base_path('docs/error-codes.json');
        $this->originalContents = is_file($this->path) ? (string) file_get_contents($this->path) : '';
    }

    protected function tearDown(): void
    {
        // The staleness test below deliberately corrupts docs/error-codes.json
        // to exercise --check's failure path. If that test fails or errors
        // before it regenerates the file, a corrupted committed file would
        // fail every subsequent `composer quality` run for unrelated reasons.
        // Restoring here, unconditionally, guarantees the committed file is
        // never left stale regardless of test outcome.
        if ($this->originalContents === '') {
            if (is_file($this->path)) {
                unlink($this->path);
            }
        } else {
            file_put_contents($this->path, $this->originalContents);
        }

        parent::tearDown();
    }

    public function test_it_writes_every_declared_code(): void
    {
        $this->runArtisan('error-codes:dump')->assertSuccessful();

        $payload = json_decode((string) file_get_contents(base_path('docs/error-codes.json')), true);

        self::assertIsArray($payload);
        self::assertIsArray($payload['codes']);
        self::assertContains('system.unexpected_error', $payload['codes']);
        self::assertSame('validation.invalid', $payload['validation_fallback']);
    }

    public function test_check_mode_passes_when_the_committed_file_is_current(): void
    {
        $this->runArtisan('error-codes:dump')->assertSuccessful();
        $this->runArtisan('error-codes:dump', ['--check' => true])->assertSuccessful();
    }

    public function test_check_mode_fails_when_the_committed_file_is_stale(): void
    {
        file_put_contents(base_path('docs/error-codes.json'), "{}\n");

        $this->runArtisan('error-codes:dump', ['--check' => true])->assertFailed();

        $this->artisan('error-codes:dump');
    }

    /**
     * InteractsWithConsole::artisan() is declared to return
     * PendingCommand|int because it falls back to the real exit code when
     * console-output mocking is disabled. This suite never disables it, so
     * the result here is always a PendingCommand; assert() lets PHPStan
     * narrow the type instead of weakening it or suppressing the mismatch.
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
