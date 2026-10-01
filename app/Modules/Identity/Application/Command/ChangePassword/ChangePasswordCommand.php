<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Command\ChangePassword;

final readonly class ChangePasswordCommand
{
    public function __construct(
        public string $userId,
        public string $currentPassword,
        public string $newPassword,
        public string $currentTokenId,
    ) {}
}
