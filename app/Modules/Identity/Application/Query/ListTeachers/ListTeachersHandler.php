<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Query\ListTeachers;

use App\Modules\Identity\Domain\Exception\AccessDeniedException;

final readonly class ListTeachersHandler
{
    public function __construct(private AccountListReader $reader) {}

    /** @return list<AccountListItem> */
    public function handle(ListTeachersQuery $query): array
    {
        if (! $query->actor->isAdmin()) {
            throw new AccessDeniedException;
        }

        return $this->reader->teachers();
    }
}
