<?php

declare(strict_types=1);

namespace App\Shared\Domain\Contract;

final readonly class BankOption
{
    public function __construct(
        public string $id,
        public string $text,
        public bool $correct,
    ) {}
}
