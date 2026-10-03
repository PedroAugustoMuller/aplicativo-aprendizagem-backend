<?php

declare(strict_types=1);

namespace App\Shared\Domain\Contract;

final readonly class BankQuestion
{
    /** @param list<BankOption> $options in position order */
    public function __construct(
        public string $id,
        public string $type,
        public string $statement,
        public ?string $explanation,
        public array $options,
    ) {}
}
