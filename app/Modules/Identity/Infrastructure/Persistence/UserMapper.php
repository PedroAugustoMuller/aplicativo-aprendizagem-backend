<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Persistence;

use App\Modules\Identity\Domain\Entity\User;
use App\Modules\Identity\Domain\ValueObject\Email;
use App\Modules\Identity\Domain\ValueObject\HashedPassword;
use App\Modules\Identity\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\EloquentAttribute;

final class UserMapper
{
    public function toDomain(UserModel $model): User
    {
        return new User(
            new UserId(EloquentAttribute::string($model->getKey(), 'users.id')),
            EloquentAttribute::string($model->getAttribute('name'), 'users.name'),
            new Email(EloquentAttribute::string($model->getAttribute('email'), 'users.email')),
            new HashedPassword(EloquentAttribute::string($model->getAttribute('password'), 'users.password')),
        );
    }
}
