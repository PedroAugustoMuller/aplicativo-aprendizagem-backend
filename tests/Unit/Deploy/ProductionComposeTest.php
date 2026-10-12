<?php

declare(strict_types=1);

namespace Tests\Unit\Deploy;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class ProductionComposeTest extends TestCase
{
    public function test_every_service_caps_its_docker_logs(): void
    {
        // Docker's default json-file driver never rotates: on the VM the logs
        // would grow until the disk is full.
        $compose = Yaml::parseFile(dirname(__DIR__, 3).'/docker-compose.prod.yml', Yaml::PARSE_CUSTOM_TAGS);
        self::assertIsArray($compose);
        self::assertIsArray($compose['services']);

        foreach ($compose['services'] as $name => $service) {
            self::assertIsArray($service);
            self::assertSame(
                ['driver' => 'json-file', 'options' => ['max-size' => '10m', 'max-file' => '3']],
                $service['logging'] ?? null,
                "service {$name} has no log rotation",
            );
        }
    }
}
