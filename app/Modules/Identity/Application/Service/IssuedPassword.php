<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Service;

final readonly class IssuedPassword
{
    public function __construct(
        public string $plain,
        public bool $isNew,
    ) {}
}
