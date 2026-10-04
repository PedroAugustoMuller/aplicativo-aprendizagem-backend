<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Application\Query\GetWrongQuestions;

use App\Modules\Quiz\Application\DTO\AnsweredQuestionRow;
use App\Modules\Quiz\Application\DTO\WrongQuestionView;
use App\Modules\Quiz\Application\Port\ProgressReader;
use App\Modules\Quiz\Application\Service\ProgressAccess;

final readonly class GetWrongQuestionsHandler
{
    public function __construct(private ProgressReader $reader, private ProgressAccess $access) {}

    /**
     * The study list: per bank question, its most recent answer (answered_at, then the
     * newer attempt), kept only if wrong. Newest first.
     *
     * @return list<WrongQuestionView>
     */
    public function handle(GetWrongQuestionsQuery $query): array
    {
        $scope = $this->access->scopeFor($query->actor, $query->classroomId, $query->studentId);
        $latest = [];

        foreach ($this->reader->answeredQuestions($scope->studentId, $query->topicId, $scope->subjectId) as $row) {
            $current = $latest[$row->questionId] ?? null;

            if ($current === null || [$row->answeredAt, $row->attemptStartedAt] > [$current->answeredAt, $current->attemptStartedAt]) {
                $latest[$row->questionId] = $row;
            }
        }

        $wrong = array_values(array_filter($latest, static fn (AnsweredQuestionRow $row): bool => ! $row->correct));
        usort($wrong, static fn (AnsweredQuestionRow $a, AnsweredQuestionRow $b): int => $b->answeredAt <=> $a->answeredAt);

        return array_map(WrongQuestionView::of(...), $wrong);
    }
}
