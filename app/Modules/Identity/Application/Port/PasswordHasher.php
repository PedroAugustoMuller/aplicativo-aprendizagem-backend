<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Port;

use App\Modules\Identity\Domain\ValueObject\HashedPassword;

interface PasswordHasher
{
    public function verify(string $plain, HashedPassword $hashed): bool;

    /**
     * A throwaway hash used to keep verification time constant when no user matched.
     *
     * Implementations MUST produce this hash with the same algorithm and the same
     * work factor as real user passwords — in practice, by going through the very
     * same hashing call. A cheap constant here silently destroys the defence the
     * caller is paying for: verifying a low-cost dummy returns measurably faster
     * than verifying a real hash, and the timing difference an attacker uses to
     * enumerate registered addresses comes straight back.
     */
    public function dummyHash(): HashedPassword;

    public function hash(string $plain): HashedPassword;
}
