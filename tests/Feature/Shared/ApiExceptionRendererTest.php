<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use App\Modules\Identity\Database\Seeders\DevelopmentAccountsSeeder;
use App\Shared\Domain\Exception\BusinessRuleException;
use App\Shared\Domain\Exception\ForbiddenException;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

final class ApiExceptionRendererTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unhandled_exception_never_leaks_details(): void
    {
        Route::get('/api/v1/__test/boom', function (): never {
            throw new RuntimeException('connection string with a password in it');
        });

        $response = $this->getJson('/api/v1/__test/boom');

        $response->assertStatus(500)
            ->assertJsonPath('error.code', 'system.unexpected_error');

        self::assertStringNotContainsString('connection string', $response->getContent() ?: '');
        self::assertStringNotContainsString('RuntimeException', $response->getContent() ?: '');
        self::assertNotEmpty($response->json('error.trace_id'));
    }

    public function test_a_domain_exception_becomes_its_own_code_and_status(): void
    {
        Route::get('/api/v1/__test/domain', function (): never {
            throw new class extends BusinessRuleException
            {
                public function errorCode(): string
                {
                    return 'content.topic.name_already_taken';
                }

                public function params(): array
                {
                    return ['name' => 'Ligações Químicas'];
                }
            };
        });

        $this->getJson('/api/v1/__test/domain')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'content.topic.name_already_taken')
            ->assertJsonPath('error.params.name', 'Ligações Químicas');
    }

    public function test_validation_failures_become_per_field_codes(): void
    {
        Route::post('/api/v1/__test/real-validation', function (Request $request) {
            $request->validate(['email' => ['required', 'email']]);

            return response()->json(['data' => true]);
        });

        $this->postJson('/api/v1/__test/real-validation', [])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation.failed')
            ->assertJsonPath('errors.email.0.code', 'validation.required')
            ->assertJsonPath('errors.email.0.params.attribute', 'email');
    }

    public function test_a_missing_api_route_returns_the_envelope(): void
    {
        $this->getJson('/api/v1/__test/nothing-here')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'http.not_found');
    }

    public function test_a_raw_authorization_exception_becomes_forbidden(): void
    {
        Route::get('/api/v1/__test/authorization', function (): never {
            throw new AuthorizationException;
        });

        $this->getJson('/api/v1/__test/authorization')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'auth.forbidden');
    }

    public function test_a_gate_denial_becomes_forbidden(): void
    {
        Gate::define('__test-never-allowed', fn (): bool => false);

        Route::get('/api/v1/__test/gate-denial', function (): void {
            Gate::authorize('__test-never-allowed');
        });

        $this->getJson('/api/v1/__test/gate-denial')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'auth.forbidden');
    }

    public function test_a_generic_http_exception_uses_its_own_status_code(): void
    {
        Route::get('/api/v1/__test/too-large', function (): never {
            abort(413);
        });

        $this->getJson('/api/v1/__test/too-large')
            ->assertStatus(413)
            ->assertJsonPath('error.code', 'http.error');
    }

    public function test_a_custom_validation_rule_does_not_leak_its_class_name(): void
    {
        Route::post('/api/v1/__test/custom-rule-validation', function (Request $request) {
            $request->validate([
                'name' => [
                    new class implements ValidationRule
                    {
                        public function validate(string $attribute, mixed $value, Closure $fail): void
                        {
                            $fail('always fails');
                        }
                    },
                ],
            ]);

            return response()->json(['data' => true]);
        });

        $response = $this->postJson('/api/v1/__test/custom-rule-validation', ['name' => 'x']);

        $response->assertStatus(422)
            ->assertJsonPath('errors.name.0.code', 'validation.invalid');

        self::assertStringNotContainsString('\\', $response->getContent() ?: '');
        self::assertStringNotContainsString('App\\', $response->getContent() ?: '');
    }

    public function test_an_http_exception_status_500_is_logged_with_its_trace_id(): void
    {
        $spy = Log::spy();

        Route::get('/api/v1/__test/abort-500', function (): never {
            abort(500, 'a raw HttpException, not a DomainException');
        });

        $response = $this->getJson('/api/v1/__test/abort-500');

        $response->assertStatus(500)->assertJsonPath('error.code', 'http.error');
        $traceId = $response->json('error.trace_id');
        self::assertIsString($traceId);
        self::assertNotEmpty($traceId);

        $spy->shouldHaveReceived('error')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => ($context['trace_id'] ?? null) === $traceId);
    }

    public function test_a_unique_rule_does_not_leak_the_table_or_column_name(): void
    {
        $this->seed(DevelopmentAccountsSeeder::class);

        Route::post('/api/v1/__test/unique-validation', function (Request $request) {
            $request->validate(['email' => ['required', 'unique:users,email']]);

            return response()->json(['data' => true]);
        });

        $response = $this->postJson('/api/v1/__test/unique-validation', ['email' => 'ana@escola.br']);

        $response->assertStatus(422)
            ->assertJsonPath('errors.email.0.code', 'validation.unique')
            ->assertJsonPath('errors.email.0.params.attribute', 'email')
            ->assertJsonMissingPath('errors.email.0.params.arg0')
            ->assertJsonMissingPath('errors.email.0.params.arg1');

        self::assertStringNotContainsString('users', $response->getContent() ?: '');
    }

    public function test_a_forbidden_exception_returns_403_with_trace_id(): void
    {
        Route::get('/api/v1/__test/forbidden', function (): never {
            throw new class extends ForbiddenException
            {
                public function errorCode(): string
                {
                    return 'auth.forbidden';
                }

                public function params(): array
                {
                    return [];
                }
            };
        });

        $response = $this->getJson('/api/v1/__test/forbidden');

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'auth.forbidden');

        self::assertIsString($response->json('error.trace_id'));
        self::assertNotEmpty($response->json('error.trace_id'));
    }

    public function test_non_api_requests_keep_laravel_default_handling(): void
    {
        Route::get('/__test/web-boom', function (): never {
            throw new RuntimeException('web');
        });

        $response = $this->get('/__test/web-boom');

        $response->assertStatus(500);
        self::assertStringNotContainsString('application/json', $response->headers->get('Content-Type') ?? '');
    }
}
