<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\DTO;

/** One row of a batch of freshly issued accounts, e.g. after creating students in bulk. */
final readonly class IssuedCredential
{
    public function __construct(
        public string $userId,
        public string $name,
        public string $login,
        public string $temporaryPassword,
    ) {}
}
