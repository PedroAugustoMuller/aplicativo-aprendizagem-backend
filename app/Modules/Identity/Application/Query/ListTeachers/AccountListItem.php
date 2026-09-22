<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Query\ListTeachers;

final readonly class AccountListItem
{
    public function __construct(
        public string $id,
        public string $name,
        public string $login,
        public bool $mustChangePassword,
        public bool $active,
    ) {}
}
