<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity\Support;

use App\Modules\Identity\Domain\Entity\User;
use App\Modules\Identity\Domain\Repository\UserRepository;
use App\Modules\Identity\Domain\ValueObject\Email;
use App\Modules\Identity\Domain\ValueObject\UserId;
use App\Modules\Identity\Domain\ValueObject\Username;

final class InMemoryUserRepository implements UserRepository
{
    /** @var array<string, User> */
    public array $byId = [];

    public function findById(UserId $id): ?User
    {
        return $this->byId[$id->value()] ?? null;
    }

    public function findByEmail(Email $email): ?User
    {
        foreach ($this->byId as $user) {
            if ($user->email()?->equals($email) === true) {
                return $user;
            }
        }

        return null;
    }

    public function findByUsername(Username $username): ?User
    {
        foreach ($this->byId as $user) {
            if ($user->username()?->value() === $username->value()) {
                return $user;
            }
        }

        return null;
    }

    public function emailExists(Email $email): bool
    {
        return $this->findByEmail($email) !== null;
    }

    public function usernameExists(string $username): bool
    {
        foreach ($this->byId as $user) {
            if ($user->username()?->value() === $username) {
                return true;
            }
        }

        return false;
    }

    public function save(User $user): void
    {
        $this->byId[$user->id()->value()] = $user;
    }
}
