<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Http\Middleware;

use App\Modules\Identity\Domain\Exception\AccessDeniedException;
use App\Modules\Identity\Infrastructure\Persistence\UserModel;
use App\Shared\Domain\Auth\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureRole
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next, string $required): Response
    {
        $user = $request->user();
        $attribute = $user instanceof UserModel ? $user->getAttribute('role') : null;
        $role = is_string($attribute) ? Role::tryFrom($attribute) : null;

        $allowed = match ($required) {
            'staff' => $role?->isStaff() === true,
            default => $role !== null && $role->value === $required,
        };

        if (! $allowed) {
            throw new AccessDeniedException;
        }

        return $next($request);
    }
}
