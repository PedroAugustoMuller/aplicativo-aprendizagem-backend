<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Http\Middleware;

use App\Modules\Identity\Domain\Exception\AccountDeactivatedException;
use App\Modules\Identity\Infrastructure\Persistence\UserModel;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAccountActive
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof UserModel && $user->getAttribute('deactivated_at') !== null) {
            // Deactivation already revoked every token; this catches a token minted
            // in the race window, and makes the rule hold even if revocation failed.
            $user->currentAccessToken()?->delete();

            throw new AccountDeactivatedException;
        }

        return $next($request);
    }
}
