<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity\Support;

use App\Modules\Identity\Application\Port\PasswordHasher;
use App\Modules\Identity\Domain\ValueObject\HashedPassword;

/** Fake hash is "hash:<plain>" so tests can assert what was hashed. */
final class FakePasswordHasher implements PasswordHasher
{
    public function verify(string $plain, HashedPassword $hashed): bool
    {
        return $hashed->value() === 'hash:'.$plain;
    }

    public function dummyHash(): HashedPassword
    {
        return new HashedPassword('hash:never');
    }

    public function hash(string $plain): HashedPassword
    {
        return new HashedPassword('hash:'.$plain);
    }
}
