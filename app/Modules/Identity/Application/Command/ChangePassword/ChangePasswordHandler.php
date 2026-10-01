<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Command\ChangePassword;

use App\Modules\Identity\Application\Port\CredentialVault;
use App\Modules\Identity\Application\Port\PasswordHasher;
use App\Modules\Identity\Application\Port\TokenRevoker;
use App\Modules\Identity\Application\Port\TransactionManager;
use App\Modules\Identity\Domain\Exception\CurrentPasswordInvalidException;
use App\Modules\Identity\Domain\Repository\UserRepository;
use App\Modules\Identity\Domain\ValueObject\UserId;
use RuntimeException;

final readonly class ChangePasswordHandler
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $hasher,
        private CredentialVault $vault,
        private TokenRevoker $tokens,
        private TransactionManager $transactions,
    ) {}

    public function handle(ChangePasswordCommand $command): void
    {
        $id = new UserId($command->userId);
        $user = $this->users->findById($id) ?? throw new RuntimeException('Authenticated user no longer exists.');

        if (! $this->hasher->verify($command->currentPassword, $user->password())) {
            throw new CurrentPasswordInvalidException;
        }

        $user->changePassword($this->hasher->hash($command->newPassword));

        $this->transactions->run(function () use ($user, $command): void {
            $this->users->save($user);
            $this->vault->forget($user->id());
            // Every other device signed in with the old password loses access.
            $this->tokens->revokeAllExcept($user->id(), $command->currentTokenId);
        });
    }
}
