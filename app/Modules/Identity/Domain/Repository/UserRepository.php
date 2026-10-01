<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Repository;

use App\Modules\Identity\Domain\Entity\User;
use App\Modules\Identity\Domain\ValueObject\Email;
use App\Modules\Identity\Domain\ValueObject\UserId;
use App\Modules\Identity\Domain\ValueObject\Username;

interface UserRepository
{
    public function findById(UserId $id): ?User;

    public function findByEmail(Email $email): ?User;

    public function findByUsername(Username $username): ?User;

    public function emailExists(Email $email): bool;

    public function usernameExists(string $username): bool;

    public function save(User $user): void;
}
