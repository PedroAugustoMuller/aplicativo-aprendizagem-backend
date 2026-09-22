<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Query\ListClassrooms;

interface ClassroomListReader
{
    /** @return list<ClassroomListItem> */
    public function all(): array;

    /** @return list<ClassroomListItem> */
    public function taughtBy(string $userId): array;

    /** @return list<ClassroomListItem> */
    public function enrolledBy(string $userId): array;
}
