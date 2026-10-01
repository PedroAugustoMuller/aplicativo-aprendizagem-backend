<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Query\ListTeachers;

interface AccountListReader
{
    /** @return list<AccountListItem> */
    public function teachers(): array;

    /** @return list<AccountListItem> */
    public function studentsOf(string $classroomId): array;
}
