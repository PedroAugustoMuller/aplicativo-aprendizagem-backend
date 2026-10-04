<?php

declare(strict_types=1);

namespace App\Shared\Domain\Contract;

final readonly class RosterStudent
{
    public function __construct(
        public string $id,
        public string $name,
        public string $username,
    ) {}
}
