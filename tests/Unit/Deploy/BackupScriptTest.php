<?php

declare(strict_types=1);

namespace Tests\Unit\Deploy;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class BackupScriptTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/backup-script-'.bin2hex(random_bytes(6));
        mkdir($this->dir);
        mkdir($this->dir.'/bin');
        mkdir($this->dir.'/backups');
        // Stands in for `docker compose exec postgres pg_dump`.
        file_put_contents($this->dir.'/bin/docker', "#!/bin/sh\necho 'CREATE TABLE users ();'\n");
        chmod($this->dir.'/bin/docker', 0755);
    }

    protected function tearDown(): void
    {
        foreach (['/bin/*', '/backups/*'] as $pattern) {
            foreach (glob($this->dir.$pattern) ?: [] as $file) {
                unlink($file);
            }
        }
        rmdir($this->dir.'/bin');
        rmdir($this->dir.'/backups');
        rmdir($this->dir);
    }

    public function test_rotation_also_drops_partial_dumps_left_by_a_failed_run(): void
    {
        $stale = $this->backup('quimica-2026-01-01-0300.sql.gz.partial', daysOld: 2);
        $recent = $this->backup('quimica-2026-01-02-0300.sql.gz', daysOld: 2);
        $old = $this->backup('quimica-2025-12-01-0300.sql.gz', daysOld: 20);

        $process = new Process(
            ['bash', dirname(__DIR__, 3).'/deploy/backup.sh'],
            env: ['PATH' => $this->dir.'/bin:'.getenv('PATH'), 'BACKUP_DIR' => $this->dir.'/backups'],
        );
        $process->run();

        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertFileDoesNotExist($stale);
        self::assertFileDoesNotExist($old);
        self::assertFileExists($recent);
        $fresh = glob($this->dir.'/backups/quimica-*.sql.gz') ?: [];
        self::assertCount(2, $fresh);
    }

    private function backup(string $name, int $daysOld): string
    {
        $path = $this->dir.'/backups/'.$name;
        file_put_contents($path, 'dump');
        touch($path, time() - $daysOld * 86400);

        return $path;
    }
}
