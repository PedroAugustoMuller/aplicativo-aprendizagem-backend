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
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));

        // A classroom shares one public IP. Keying login on IP alone (the
        // previous `throttle:5,1` on the route) gives the whole class a
        // combined budget of five login attempts per minute. This combines
        // two limits instead: a tight per-account bucket (email + IP) that
        // still caps brute-forcing one account at 5/minute, and a much wider
        // per-IP bucket that only trips if the same IP is hammering many
        // accounts — a real attack, not thirty students opening the app for
        // a lesson. Email is lowercased/trimmed so case variants of the same
        // address share one bucket instead of getting a fresh one each.
        RateLimiter::for('login', function (Request $request): array {
            $email = mb_strtolower(trim((string) $request->string('email')));

            return [
                Limit::perMinute(5)->by($email.'|'.$request->ip()),
                Limit::perMinute(60)->by($request->ip()),
            ];
        });
    }
}
