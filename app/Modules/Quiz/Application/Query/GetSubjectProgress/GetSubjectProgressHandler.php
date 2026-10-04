<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Application\Query\GetSubjectProgress;

use App\Modules\Quiz\Application\DTO\TopicProgressView;
use App\Modules\Quiz\Application\Port\ProgressReader;
use App\Modules\Quiz\Application\Service\ProgressAccess;
use App\Modules\Quiz\Domain\Service\Progress;

final readonly class GetSubjectProgressHandler
{
    public function __construct(private ProgressReader $reader, private ProgressAccess $access) {}

    /** @return list<TopicProgressView> only topics with at least one graded answer */
    public function handle(GetSubjectProgressQuery $query): array
    {
        $scope = $this->access->ownScope($query->actor);
        $views = [];

        foreach ($this->reader->answersBySubject($scope->studentId, $query->subjectId) as $topicId => $answers) {
            $views[] = TopicProgressView::of((string) $topicId, Progress::fromAnswers($answers));
        }

        return $views;
    }
}
