<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Console;

use App\Shared\Domain\Error\SystemErrorCode;
use App\Shared\Infrastructure\Error\ErrorCodeRegistry;
use Illuminate\Console\Command;

final class DumpErrorCodesCommand extends Command
{
    protected $signature = 'error-codes:dump {--check : Fail instead of writing when the committed file is stale}';

    protected $description = 'Write every declared error code to docs/error-codes.json';

    public function handle(ErrorCodeRegistry $registry): int
    {
        $path = base_path('docs/error-codes.json');

        $payload = json_encode([
            'generated_by' => 'php artisan error-codes:dump',
            'validation_fallback' => SystemErrorCode::ValidationInvalid->value,
            'codes' => $registry->all(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";

        if ($this->option('check')) {
            $current = is_file($path) ? (string) file_get_contents($path) : '';

            if ($current !== $payload) {
                $this->components->error('docs/error-codes.json is stale. Run: php artisan error-codes:dump');

                return self::FAILURE;
            }

            $this->components->info('docs/error-codes.json is up to date.');

            return self::SUCCESS;
        }

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0o775, true);
        }

        file_put_contents($path, $payload);
        $this->components->info(sprintf('Wrote %d error codes to docs/error-codes.json', count($registry->all())));

        return self::SUCCESS;
    }
}
