<?php

declare(strict_types=1);

namespace Tests\Unit\Deploy;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class CloudflareIpsScriptTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/cloudflare-ips-'.bin2hex(random_bytes(6));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);
    }

    public function test_keeps_the_last_range_when_the_list_has_no_trailing_newline(): void
    {
        // Cloudflare serves both lists without a final newline.
        $this->writeLists(
            v4: implode("\n", $this->ranges('10.%d.0.0/16', 5))."\n131.0.72.0/22",
            v6: implode("\n", $this->ranges('2400:cb%02d::/32', 5))."\n2c0f:f248::/32",
        );

        $process = $this->runScript();

        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $config = (string) file_get_contents($this->dir.'/out.conf');
        self::assertStringContainsString("set_real_ip_from 131.0.72.0/22;\n", $config);
        self::assertStringContainsString("set_real_ip_from 2c0f:f248::/32;\n", $config);
        self::assertSame(12, substr_count($config, 'set_real_ip_from '));
    }

    public function test_refuses_an_unexpected_line_and_leaves_the_output_untouched(): void
    {
        $this->writeLists(
            v4: implode("\n", $this->ranges('10.%d.0.0/16', 10))."\n<html>",
            v6: implode("\n", $this->ranges('2400:cb%02d::/32', 5)),
        );
        file_put_contents($this->dir.'/out.conf', 'previous');

        $process = $this->runScript();

        self::assertNotSame(0, $process->getExitCode());
        self::assertStringContainsString('unexpected line from ips-v4', $process->getErrorOutput());
        self::assertSame('previous', file_get_contents($this->dir.'/out.conf'));
    }

    private function writeLists(string $v4, string $v6): void
    {
        file_put_contents($this->dir.'/ips-v4', $v4);
        file_put_contents($this->dir.'/ips-v6', $v6);
    }

    /** @return list<string> */
    private function ranges(string $format, int $count): array
    {
        return array_map(static fn (int $i): string => sprintf($format, $i), range(1, $count));
    }

    private function runScript(): Process
    {
        $process = new Process(
            ['bash', dirname(__DIR__, 3).'/deploy/cloudflare-ips.sh', $this->dir.'/out.conf'],
            env: ['CLOUDFLARE_IPS_BASE_URL' => 'file://'.$this->dir],
        );
        $process->run();

        return $process;
    }
}
