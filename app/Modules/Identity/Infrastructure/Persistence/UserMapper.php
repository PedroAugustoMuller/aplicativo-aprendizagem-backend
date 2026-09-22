<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Persistence;

use App\Modules\Identity\Domain\Entity\User;
use App\Modules\Identity\Domain\ValueObject\Email;
use App\Modules\Identity\Domain\ValueObject\HashedPassword;
use App\Modules\Identity\Domain\ValueObject\UserId;
use App\Modules\Identity\Domain\ValueObject\Username;
use App\Shared\Domain\Auth\Role;
use App\Shared\Infrastructure\Persistence\EloquentAttribute;
use DateTimeImmutable;

final class UserMapper
{
    public function toDomain(UserModel $model): User
    {
        $email = $model->getAttribute('email');
        $username = $model->getAttribute('username');
        $deactivatedAt = $model->getAttribute('deactivated_at');

        return User::restore(
            new UserId(EloquentAttribute::string($model->getKey(), 'users.id')),
            EloquentAttribute::string($model->getAttribute('name'), 'users.name'),
            Role::from(EloquentAttribute::string($model->getAttribute('role'), 'users.role')),
            $email === null ? null : new Email(EloquentAttribute::string($email, 'users.email')),
            $username === null ? null : new Username(EloquentAttribute::string($username, 'users.username')),
            new HashedPassword(EloquentAttribute::string($model->getAttribute('password'), 'users.password')),
            $model->getAttribute('must_change_password') === true,
            $deactivatedAt instanceof DateTimeImmutable ? $deactivatedAt : null,
        );
    }

    /** @return array<string, string|bool|DateTimeImmutable|null> */
    public function toAttributes(User $user): array
    {
        return [
            'name' => $user->name(),
            'role' => $user->role()->value,
            'email' => $user->email()?->value(),
            'username' => $user->username()?->value(),
            'password' => $user->password()->value(),
            'must_change_password' => $user->mustChangePassword(),
            'deactivated_at' => $user->deactivatedAt(),
        ];
    }
}
