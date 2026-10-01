<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use App\Shared\Domain\Auth\Actor;
use App\Shared\Domain\Auth\Role;
use App\Shared\Infrastructure\Persistence\EloquentAttribute;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Builds the per-request Actor from the authenticated user. Lives in Shared, not
 * Identity, because both Identity and Content need it — it narrows only to the
 * framework's Model contract, never to Identity's own UserModel.
 */
final class ActorFactory
{
    public function fromRequest(Request $request): Actor
    {
        $user = $request->user();

        if (! $user instanceof Model) {
            throw new AuthenticationException;
        }

        return new Actor(
            EloquentAttribute::string($user->getKey(), 'users.id'),
            Role::from(EloquentAttribute::string($user->getAttribute('role'), 'users.role')),
        );
    }
}
