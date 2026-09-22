<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Http\Middleware;

use App\Modules\Identity\Domain\Exception\PasswordChangeRequiredException;
use App\Modules\Identity\Infrastructure\Persistence\UserModel;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePasswordChanged
{
    /** Routes a user with a temporary password can still reach, by name. */
    private const ALLOWED = ['auth.me', 'auth.logout', 'auth.password'];

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user instanceof UserModel
            && $user->getAttribute('must_change_password') === true
            && ! in_array($request->route()?->getName(), self::ALLOWED, true)
        ) {
            throw new PasswordChangeRequiredException;
        }

        return $next($request);
    }
}
