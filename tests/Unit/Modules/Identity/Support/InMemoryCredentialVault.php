<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity\Support;

use App\Modules\Identity\Application\Port\CredentialVault;
use App\Modules\Identity\Domain\ValueObject\UserId;

final class InMemoryCredentialVault implements CredentialVault
{
    /** @var array<string, string> */
    public array $stored = [];

    public function store(UserId $id, string $plainPassword): void
    {
        $this->stored[$id->value()] = $plainPassword;
    }

    public function reveal(UserId $id): ?string
    {
        return $this->stored[$id->value()] ?? null;
    }

    /**
     * @param  list<UserId>  $ids
     * @return array<string, string>
     */
    public function revealMany(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            if (isset($this->stored[$id->value()])) {
                $out[$id->value()] = $this->stored[$id->value()];
            }
        }

        return $out;
    }

    public function forget(UserId $id): void
    {
        unset($this->stored[$id->value()]);
    }
}
