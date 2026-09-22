<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Port;

use App\Modules\Identity\Domain\ValueObject\UserId;

/**
 * Holds the plaintext of a password someone else chose for a user (a temporary
 * password or a reset), so staff can reprint a lost slip until the owner replaces
 * it. Entries are encrypted at rest and removed the moment the user changes it.
 */
interface CredentialVault
{
    public function store(UserId $id, string $plainPassword): void;

    public function reveal(UserId $id): ?string;

    public function forget(UserId $id): void;

    /**
     * @param  list<UserId>  $ids
     * @return array<string, string> userId => password
     */
    public function revealMany(array $ids): array;
}
