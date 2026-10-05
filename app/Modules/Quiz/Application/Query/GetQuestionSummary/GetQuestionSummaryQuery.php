<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Application\Query\GetQuestionSummary;

use App\Shared\Domain\Auth\Actor;

/** Without a classroom: every active classroom of the topic's subject the actor may read. */
final readonly class GetQuestionSummaryQuery
{
    public function __construct(
        public Actor $actor,
        public string $topicId,
        public ?string $classroomId = null,
    ) {}
}
