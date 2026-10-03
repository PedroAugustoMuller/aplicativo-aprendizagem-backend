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

    public function handle(ListTopicsQuery $query): TopicList
    {
        if ($this->subjects->findById(new SubjectId($query->subjectId)) === null) {
            throw new SubjectNotFoundException;
        }

        if (! $this->policy->canView($query->actor, $query->subjectId)) {
            throw new ContentAccessDeniedException;
        }

        // Authors manage deactivated topics too; everyone else only ever sees active ones.
        $canAuthor = $this->policy->canAuthor($query->actor, $query->subjectId);

        return new TopicList($canAuthor, $this->reader->forSubject($query->subjectId, includeInactive: $canAuthor));
    }
}
