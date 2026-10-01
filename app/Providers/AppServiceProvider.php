<?php

declare(strict_types=1);

namespace App\Providers;

use App\Shared\Domain\Error\ErrorCode;
use App\Shared\Infrastructure\Error\ErrorCodeRegistry;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use UnitEnum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            ErrorCodeRegistry::class,
            function (Application $app): ErrorCodeRegistry {
                /** @var list<class-string<ErrorCode&UnitEnum>> $enums */
                $enums = $app->make('config')->get('error_codes.enums', []);

                return new ErrorCodeRegistry($enums);
            },
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Keying on $request->user() here would always fall back to the IP: this
        // limiter is bound to the `api` middleware GROUP (prepended in
        // bootstrap/app.php) which runs before the route-level `auth:sanctum`
        // middleware ever authenticates the request, and the default guard
        // (config/auth.php) is session-based `web`, not `sanctum` — so
        // $request->user() is null here even for a request carrying a valid
        // bearer token. Read the token straight off the request instead and key
        // on a hash of it (never the raw token — it would otherwise sit in the
        // cache store as a plaintext credential); anonymous requests still fall
        // back to the IP. Do not "simplify" this back to $request->user().
        RateLimiter::for('api', function (Request $request): Limit {
            $token = $request->bearerToken();

            return Limit::perMinute(120)->by(
                is_string($token) ? 'token:'.hash('sha256', $token) : $request->ip(),
            );
        });

        // A classroom shares one public IP. Keying login on IP alone (the
        // previous `throttle:5,1` on the route) gives the whole class a
        // combined budget of five login attempts per minute. This combines
        // two limits instead: a tight per-account bucket (login + IP) that
        // still caps brute-forcing one account at 5/minute, and a much wider
        // per-IP bucket that only trips if the same IP is hammering many
        // accounts — a real attack, not thirty students opening the app for
        // a lesson. Email or username. Lowercased and trimmed so case variants
        // share one bucket instead of getting a fresh one each.
        RateLimiter::for('login', function (Request $request): array {
            $login = mb_strtolower(trim((string) $request->string('login')));

            return [
                Limit::perMinute(5)->by($login.'|'.$request->ip()),
                Limit::perMinute(60)->by($request->ip()),
            ];
        });

        RateLimiter::for('password', function (Request $request): Limit {
            $identifier = $request->user()?->getAuthIdentifier();

            return Limit::perMinute(5)->by('password|'.(is_string($identifier) ? $identifier : $request->ip()));
        });
    }
}
