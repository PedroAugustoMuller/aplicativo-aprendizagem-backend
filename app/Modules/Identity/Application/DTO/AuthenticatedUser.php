<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\DTO;

final readonly class AuthenticatedUser
{
    public function __construct(
        public string $id,
        public string $name,
        public string $login,
        public string $role,
        public bool $mustChangePassword,
        public string $token,
    ) {}
}
