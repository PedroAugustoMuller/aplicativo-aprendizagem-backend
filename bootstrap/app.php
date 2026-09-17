<?php

declare(strict_types=1);

use App\Shared\Infrastructure\Console\DumpErrorCodesCommand;
use App\Shared\Infrastructure\Http\ApiExceptionRenderer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        DumpErrorCodesCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            'throttle:api',
        ]);

        // A pure API has no `login` route to redirect a guest to. Without this,
        // Laravel's Authenticate middleware resolves route('login') for any
        // request that does not expect JSON and throws RouteNotFoundException
        // from inside the middleware — surfacing as a 500 system.unexpected_error
        // instead of the 401 auth.unauthenticated our renderer would otherwise
        // produce.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : '/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(
            fn (Throwable $e, Request $request) => app(ApiExceptionRenderer::class)->render($e, $request),
        );
    })->create();
