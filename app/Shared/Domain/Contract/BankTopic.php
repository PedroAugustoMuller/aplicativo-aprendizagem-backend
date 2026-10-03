<?php

declare(strict_types=1);

namespace App\Shared\Domain\Contract;

/** A topic as a quiz sees it. `available`: the topic and its subject are both active. */
final readonly class BankTopic
{
    public function __construct(
        public string $id,
        public string $subjectId,
        public bool $available,
    ) {}
}
