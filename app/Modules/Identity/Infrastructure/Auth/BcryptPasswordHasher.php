<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Auth;

use App\Modules\Identity\Application\Port\PasswordHasher;
use App\Modules\Identity\Domain\ValueObject\HashedPassword;
use Illuminate\Support\Facades\Hash;

final class BcryptPasswordHasher implements PasswordHasher
{
    public function verify(string $plain, HashedPassword $hashed): bool
    {
        return Hash::check($plain, $hashed->value());
    }

    public function dummyHash(): HashedPassword
    {
        // Deliberately hashes for real rather than returning a fixed string: this
        // goes through the same Hash::make() as genuine passwords, so it inherits
        // the configured work factor and verifying it costs the same as verifying
        // a real hash. That equal cost IS the timing defence — a cheap constant
        // would look equivalent and quietly defeat it.
        return new HashedPassword(Hash::make('never-matches-'.bin2hex(random_bytes(8))));
    }

    public function hash(string $plain): HashedPassword
    {
        return new HashedPassword(Hash::make($plain));
    }
}
