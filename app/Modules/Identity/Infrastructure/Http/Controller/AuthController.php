<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Http\Controller;

use App\Modules\Identity\Application\Command\AuthenticateUser\AuthenticateUserCommand;
use App\Modules\Identity\Application\Command\AuthenticateUser\AuthenticateUserHandler;
use App\Modules\Identity\Infrastructure\Http\Request\LoginRequest;
use App\Modules\Identity\Infrastructure\Http\Resource\AuthenticatedUserResource;
use App\Modules\Identity\Infrastructure\Persistence\UserModel;
use App\Shared\Infrastructure\Persistence\EloquentAttribute;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class AuthController
{
    public function login(LoginRequest $request, AuthenticateUserHandler $handler): JsonResponse
    {
        $result = $handler->handle(new AuthenticateUserCommand(
            email: (string) $request->string('email'),
            password: (string) $request->string('password'),
        ));

        return new JsonResponse(AuthenticatedUserResource::make($result));
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        // auth:sanctum already guarantees a resolved user, and the configured
        // provider model is UserModel, so this branch cannot fire in practice.
        // It exists so PHPStan can narrow Request::user()'s ?Authenticatable to
        // a concrete UserModel below.
        if (! $user instanceof UserModel) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse(['data' => [
            'id' => EloquentAttribute::string($user->getKey(), 'users.id'),
            'name' => EloquentAttribute::string($user->getAttribute('name'), 'users.name'),
            'email' => EloquentAttribute::string($user->getAttribute('email'), 'users.email'),
        ]]);
    }

    public function logout(Request $request): Response
    {
        $user = $request->user();

        if ($user instanceof UserModel) {
            $user->currentAccessToken()?->delete();
        }

        return response()->noContent();
    }
}
