<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Application\Query\GetTopicHistory;

use App\Modules\Quiz\Application\DTO\AttemptSummaryRow;
use App\Modules\Quiz\Application\DTO\AttemptSummaryView;
use App\Modules\Quiz\Application\DTO\TopicHistoryView;
use App\Modules\Quiz\Application\Port\ProgressReader;
use App\Modules\Quiz\Application\Service\ProgressAccess;
use App\Modules\Quiz\Domain\Service\Progress;

final readonly class GetTopicHistoryHandler
{
    public function __construct(private ProgressReader $reader, private ProgressAccess $access) {}

    public function handle(GetTopicHistoryQuery $query): TopicHistoryView
    {
        $scope = $this->access->scopeFor($query->actor, $query->classroomId, $query->studentId);
        $progress = Progress::fromAnswers($this->reader->answersForTopic($scope->studentId, $query->topicId, $scope->subjectId));

        return new TopicHistoryView(
            $progress->points,
            $progress->tier(),
            $progress->nextTier(),
            array_map(
                static fn (AttemptSummaryRow $row): AttemptSummaryView => AttemptSummaryView::of($row, $progress->forAttempt($row->id)),
                $this->reader->attemptsForTopic($scope->studentId, $query->topicId, $scope->subjectId),
            ),
        );
    }
}
