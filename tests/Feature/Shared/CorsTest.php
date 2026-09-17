<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use Tests\TestCase;

final class CorsTest extends TestCase
{
    public function test_it_allows_the_configured_frontend_origin(): void
    {
        config(['cors.allowed_origins' => ['http://localhost:5173']]);

        $this->call('OPTIONS', '/api/v1/topics', server: [
            'HTTP_ORIGIN' => 'http://localhost:5173',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ])->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
    }

    /**
     * Despite the name, this does not prove the attacker's Origin header is
     * inspected and rejected. With exactly one configured origin,
     * fruitcake/php-cors takes its isSingleOriginAllowed() branch and emits
     * that one configured origin as a constant, without reading the incoming
     * Origin header at all — the attacker's value is ignored, not checked.
     * This is still safe (the browser compares the header against its own
     * request's origin and blocks on mismatch, and supports_credentials is
     * false so there is no cookie session to leak), but nobody should read
     * this test as proof that origin validation is happening.
     */
    public function test_it_does_not_allow_an_arbitrary_origin(): void
    {
        config(['cors.allowed_origins' => ['http://localhost:5173']]);

        $response = $this->call('OPTIONS', '/api/v1/topics', server: [
            'HTTP_ORIGIN' => 'https://attacker.example',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ]);

        self::assertNotSame('*', $response->headers->get('Access-Control-Allow-Origin'));
        self::assertNotSame('https://attacker.example', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_the_wildcard_origin_is_never_configured(): void
    {
        self::assertNotContains('*', (array) config('cors.allowed_origins'));
    }

    public function test_credentialed_cors_is_never_enabled(): void
    {
        self::assertFalse(config('cors.supports_credentials'));

        $this->call('OPTIONS', '/api/v1/topics', server: [
            'HTTP_ORIGIN' => 'http://localhost:5173',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ])->assertHeaderMissing('Access-Control-Allow-Credentials');
    }
}
