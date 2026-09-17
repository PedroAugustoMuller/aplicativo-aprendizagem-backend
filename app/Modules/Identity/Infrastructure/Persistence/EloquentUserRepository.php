<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Persistence;

use App\Modules\Identity\Domain\Entity\User;
use App\Modules\Identity\Domain\Repository\UserRepository;
use App\Modules\Identity\Domain\ValueObject\Email;

final class EloquentUserRepository implements UserRepository
{
    public function __construct(private readonly UserMapper $mapper) {}

    public function findByEmail(Email $email): ?User
    {
        $model = UserModel::query()->where('email', $email->value())->first();

        return $model instanceof UserModel ? $this->mapper->toDomain($model) : null;
    }
}
