<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Command\AuthenticateUser;

final readonly class AuthenticateUserCommand
{
    public function __construct(
        public string $email,
        public string $password,
    ) {}
}
