<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Query\ListSubjects;

use App\Shared\Domain\Contract\TeachingAssignments;

final readonly class ListSubjectsHandler
{
    public function __construct(private SubjectListReader $reader, private TeachingAssignments $assignments) {}

    /** @return list<SubjectListItem> */
    public function handle(ListSubjectsQuery $query): array
    {
        if ($query->actor->isStaff()) {
            return $this->reader->list(null);
        }

        $enrolledIds = $this->assignments->subjectIdsEnrolledBy($query->actor->userId);

        return array_values(array_filter(
            $this->reader->list($enrolledIds),
            fn (SubjectListItem $s): bool => $s->active,
        ));
    }
}
