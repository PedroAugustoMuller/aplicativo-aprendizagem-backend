<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Query\ListClassrooms;

final readonly class ListClassroomsHandler
{
    public function __construct(private ClassroomListReader $reader) {}

    /** @return list<ClassroomListItem> */
    public function handle(ListClassroomsQuery $query): array
    {
        $actor = $query->actor;

        if ($actor->isAdmin()) {
            return $this->reader->all();
        }

        return $actor->isStudent() ? $this->reader->enrolledBy($actor->userId) : $this->reader->taughtBy($actor->userId);
    }
}
