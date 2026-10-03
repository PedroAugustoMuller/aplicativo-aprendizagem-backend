<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Query\ListSubjects;

use App\Modules\Content\Domain\Policy\SubjectPolicy;
use App\Shared\Domain\Contract\TeachingAssignments;

final readonly class ListSubjectsHandler
{
    public function __construct(
        private SubjectListReader $reader,
        private TeachingAssignments $assignments,
        private SubjectPolicy $policy,
    ) {}

    /** @return list<SubjectListItem> */
    public function handle(ListSubjectsQuery $query): array
    {
        if ($query->actor->isStaff()) {
            // One lookup for the whole list, not one canAuthor() query per subject.
            $authored = $this->policy->authoredSubjectIds($query->actor);

            return array_map(
                fn (SubjectListItem $s): SubjectListItem => $s->withCanAuthor($authored === null || in_array($s->id, $authored, true)),
                $this->reader->list(null),
            );
        }

        $enrolledIds = $this->assignments->subjectIdsEnrolledBy($query->actor->userId);

        return array_values(array_filter(
            $this->reader->list($enrolledIds),
            fn (SubjectListItem $s): bool => $s->active,
        ));
    }
}
