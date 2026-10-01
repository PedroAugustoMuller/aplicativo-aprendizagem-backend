<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Service;

use App\Modules\Identity\Application\Port\CredentialVault;
use App\Modules\Identity\Application\Port\PasswordHasher;
use App\Modules\Identity\Domain\Entity\User;
use App\Modules\Identity\Domain\Service\TemporaryPasswordGenerator;

/**
 * The single place a temporary password is created. Writes nothing itself: the
 * pending_credentials row has a foreign key to users, so the caller must save the
 * user (inside a transaction) between issueFor() and remember().
 */
final readonly class PasswordIssuer
{
    public function __construct(
        private PasswordHasher $hasher,
        private CredentialVault $vault,
        private TemporaryPasswordGenerator $generator,
    ) {}

    /**
     * Idempotent while pending: a resent reset, or a teacher pressing "reset" twice,
     * must not invalidate the slip already handed to the student. Writes nothing —
     * the vault row references users, so remember() runs after the user is saved.
     */
    public function issueFor(User $user): IssuedPassword
    {
        $pending = $this->vault->reveal($user->id());
        if ($pending !== null && $user->mustChangePassword()) {
            return new IssuedPassword($pending, isNew: false);
        }

        $plain = $this->generator->generate();
        $user->resetPassword($this->hasher->hash($plain));

        return new IssuedPassword($plain, isNew: true);
    }

    public function remember(User $user, IssuedPassword $issued): void
    {
        if ($issued->isNew) {
            $this->vault->store($user->id(), $issued->plain);
        }
    }
}
