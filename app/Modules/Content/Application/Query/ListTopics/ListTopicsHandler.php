<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Query\ListTopics;

use App\Modules\Content\Domain\Exception\ContentAccessDeniedException;
use App\Modules\Content\Domain\Exception\SubjectNotFoundException;
use App\Modules\Content\Domain\Policy\SubjectPolicy;
use App\Modules\Content\Domain\Repository\SubjectRepository;
use App\Modules\Content\Domain\ValueObject\SubjectId;

final readonly class ListTopicsHandler
{
    public function __construct(
        private TopicListReader $reader,
        private SubjectRepository $subjects,
        private SubjectPolicy $policy,
    ) {}

    /** @return list<TopicListItem> */
    public function handle(ListTopicsQuery $query): array
    {
        if ($this->subjects->findById(new SubjectId($query->subjectId)) === null) {
            throw new SubjectNotFoundException;
        }

        if (! $this->policy->canView($query->actor, $query->subjectId)) {
            throw new ContentAccessDeniedException;
        }

        return $this->reader->forSubject($query->subjectId);
    }
}
