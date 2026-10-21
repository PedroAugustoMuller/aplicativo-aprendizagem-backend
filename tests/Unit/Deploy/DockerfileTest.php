<?php

declare(strict_types=1);

namespace Tests\Unit\Deploy;

use PHPUnit\Framework\TestCase;

final class DockerfileTest extends TestCase
{
    // 8.4.27 fixes PHP2Shell (CVE-2026-109618, CVE-2026-109619): unauthenticated
    // RCE through PHP-FPM.
    private const PATCHED_VERSION_ID = 80427;

    public function test_the_base_image_pins_an_exact_php_patch_release(): void
    {
        // A floating tag like 8.4-fpm lets the VM rebuild from a cached, vulnerable
        // base layer; an exact patch release forces the pull.
        $dockerfile = (string) file_get_contents(dirname(__DIR__, 3).'/Dockerfile');

        if (preg_match('/^FROM php:(\d+)\.(\d+)\.(\d+)-fpm-bookworm AS base$/m', $dockerfile, $m) !== 1) {
            self::fail('the base image is not pinned to an exact php patch release');
        }
        self::assertGreaterThanOrEqual(self::PATCHED_VERSION_ID, (int) $m[1] * 10000 + (int) $m[2] * 100 + (int) $m[3]);
    }

    public function test_the_running_php_has_the_php2shell_fix(): void
    {
        self::assertGreaterThanOrEqual(self::PATCHED_VERSION_ID, PHP_VERSION_ID, 'rebuild the image: '.PHP_VERSION);
    }
}
