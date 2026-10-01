<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Http\Controller;

use App\Modules\Identity\Application\Command\AuthenticateUser\AuthenticateUserCommand;
use App\Modules\Identity\Application\Command\AuthenticateUser\AuthenticateUserHandler;
use App\Modules\Identity\Application\Command\ChangePassword\ChangePasswordCommand;
use App\Modules\Identity\Application\Command\ChangePassword\ChangePasswordHandler;
use App\Modules\Identity\Infrastructure\Http\Request\ChangePasswordRequest;
use App\Modules\Identity\Infrastructure\Http\Request\LoginRequest;
use App\Modules\Identity\Infrastructure\Http\Resource\AuthenticatedUserResource;
use App\Modules\Identity\Infrastructure\Persistence\UserModel;
use App\Shared\Infrastructure\Persistence\EloquentAttribute;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use UnexpectedValueException;

final class AuthController
{
    public function login(LoginRequest $request, AuthenticateUserHandler $handler): JsonResponse
    {
        $result = $handler->handle(new AuthenticateUserCommand(
            login: (string) $request->string('login'),
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
            'login' => EloquentAttribute::string($user->getAttribute('email') ?? $user->getAttribute('username'), 'users.login'),
            'role' => EloquentAttribute::string($user->getAttribute('role'), 'users.role'),
            'must_change_password' => $user->getAttribute('must_change_password') === true,
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

    public function changePassword(ChangePasswordRequest $request, ChangePasswordHandler $handler): Response
    {
        $user = $request->user();
        if (! $user instanceof UserModel) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        // The token's primary key is an auto-increment bigint (see
        // create_personal_access_tokens_table's comment), so it arrives as an
        // int, not a string — EloquentAttribute::string() does not fit here.
        $tokenKey = $user->currentAccessToken()->getKey();
        if (! is_int($tokenKey) && ! is_string($tokenKey)) {
            throw new UnexpectedValueException('Expected a scalar id for the current access token.');
        }

        $handler->handle(new ChangePasswordCommand(
            userId: EloquentAttribute::string($user->getKey(), 'users.id'),
            currentPassword: (string) $request->string('current_password'),
            newPassword: (string) $request->string('new_password'),
            currentTokenId: (string) $tokenKey,
        ));

        return response()->noContent();
    }
}
