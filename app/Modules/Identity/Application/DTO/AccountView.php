<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\DTO;

use App\Modules\Identity\Domain\Entity\User;

final readonly class AccountView
{
    public function __construct(
        public string $id,
        public string $name,
        public string $login,
        public string $role,
        public bool $mustChangePassword,
        public bool $active,
        public ?string $temporaryPassword,
    ) {}

    public static function of(User $user, ?string $temporaryPassword): self
    {
        return new self(
            $user->id()->value(),
            $user->name(),
            $user->login(),
            $user->role()->value,
            $user->mustChangePassword(),
            $user->isActive(),
            $temporaryPassword,
        );
    }
}
