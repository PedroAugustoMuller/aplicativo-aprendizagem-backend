<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Persistence;

use App\Modules\Identity\Domain\Entity\User;
use App\Modules\Identity\Domain\Repository\UserRepository;
use App\Modules\Identity\Domain\ValueObject\Email;
use App\Modules\Identity\Domain\ValueObject\UserId;
use App\Modules\Identity\Domain\ValueObject\Username;

final class EloquentUserRepository implements UserRepository
{
    public function __construct(private readonly UserMapper $mapper) {}

    public function findById(UserId $id): ?User
    {
        return $this->map(UserModel::query()->find($id->value()));
    }

    public function findByEmail(Email $email): ?User
    {
        return $this->map(UserModel::query()->where('email', $email->value())->first());
    }

    public function findByUsername(Username $username): ?User
    {
        return $this->map(UserModel::query()->where('username', $username->value())->first());
    }

    public function emailExists(Email $email): bool
    {
        return UserModel::query()->where('email', $email->value())->exists();
    }

    public function usernameExists(string $username): bool
    {
        return UserModel::query()->where('username', $username)->exists();
    }

    public function save(User $user): void
    {
        UserModel::query()->updateOrCreate(['id' => $user->id()->value()], $this->mapper->toAttributes($user));
    }

    private function map(mixed $model): ?User
    {
        return $model instanceof UserModel ? $this->mapper->toDomain($model) : null;
    }
}
