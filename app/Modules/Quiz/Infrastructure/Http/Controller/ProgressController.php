<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Infrastructure\Http\Controller;

use App\Modules\Quiz\Application\DTO\AttemptSummaryView;
use App\Modules\Quiz\Application\DTO\QuestionSummaryView;
use App\Modules\Quiz\Application\DTO\StudentProgressView;
use App\Modules\Quiz\Application\DTO\TopicHistoryView;
use App\Modules\Quiz\Application\DTO\TopicProgressView;
use App\Modules\Quiz\Application\DTO\WrongQuestionView;
use App\Modules\Quiz\Application\Query\GetClassroomProgress\GetClassroomProgressHandler;
use App\Modules\Quiz\Application\Query\GetClassroomProgress\GetClassroomProgressQuery;
use App\Modules\Quiz\Application\Query\GetQuestionSummary\GetQuestionSummaryHandler;
use App\Modules\Quiz\Application\Query\GetQuestionSummary\GetQuestionSummaryQuery;
use App\Modules\Quiz\Application\Query\GetStudentAttempt\GetStudentAttemptHandler;
use App\Modules\Quiz\Application\Query\GetStudentAttempt\GetStudentAttemptQuery;
use App\Modules\Quiz\Application\Query\GetSubjectProgress\GetSubjectProgressHandler;
use App\Modules\Quiz\Application\Query\GetSubjectProgress\GetSubjectProgressQuery;
use App\Modules\Quiz\Application\Query\GetTopicHistory\GetTopicHistoryHandler;
use App\Modules\Quiz\Application\Query\GetTopicHistory\GetTopicHistoryQuery;
use App\Modules\Quiz\Application\Query\GetWrongQuestions\GetWrongQuestionsHandler;
use App\Modules\Quiz\Application\Query\GetWrongQuestions\GetWrongQuestionsQuery;
use App\Modules\Quiz\Domain\ValueObject\OptionCount;
use App\Modules\Quiz\Domain\ValueObject\QuestionStats;
use App\Modules\Quiz\Domain\ValueObject\SnapshotOption;
use App\Modules\Quiz\Domain\ValueObject\Tier;
use App\Modules\Quiz\Infrastructure\Http\Presenter\AttemptPresenter;
use App\Shared\Infrastructure\Http\ActorFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProgressController
{
    public function __construct(private readonly ActorFactory $actors, private readonly AttemptPresenter $presenter) {}

    public function subject(Request $request, string $id, GetSubjectProgressHandler $handler): JsonResponse
    {
        $views = $handler->handle(new GetSubjectProgressQuery($this->actors->fromRequest($request), $id));

        return new JsonResponse(['data' => array_map($this->topicProgress(...), $views)]);
    }

    public function history(Request $request, string $id, GetTopicHistoryHandler $handler): JsonResponse
    {
        return new JsonResponse(['data' => $this->historyOf($handler->handle(new GetTopicHistoryQuery($this->actors->fromRequest($request), $id)))]);
    }

    public function wrong(Request $request, string $id, GetWrongQuestionsHandler $handler): JsonResponse
    {
        $views = $handler->handle(new GetWrongQuestionsQuery($this->actors->fromRequest($request), $id));

        return new JsonResponse(['data' => array_map($this->wrongOf(...), $views)]);
    }

    public function classroom(Request $request, string $id, GetClassroomProgressHandler $handler): JsonResponse
    {
        $view = $handler->handle(new GetClassroomProgressQuery($this->actors->fromRequest($request), $id));

        return new JsonResponse(['data' => ['students' => array_map(static fn (StudentProgressView $s): array => [
            'id' => $s->id,
            'name' => $s->name,
            'username' => $s->username,
            'topics' => array_map(static fn (TopicProgressView $t): array => ['topic_id' => $t->topicId, 'points' => $t->points, 'tier' => $t->tier->value], $s->topics),
        ], $view->students)]]);
    }

    public function studentHistory(Request $request, string $id, string $studentId, string $topicId, GetTopicHistoryHandler $handler): JsonResponse
    {
        return new JsonResponse(['data' => $this->historyOf($handler->handle(new GetTopicHistoryQuery($this->actors->fromRequest($request), $topicId, $id, $studentId)))]);
    }

    public function studentWrong(Request $request, string $id, string $studentId, string $topicId, GetWrongQuestionsHandler $handler): JsonResponse
    {
        $views = $handler->handle(new GetWrongQuestionsQuery($this->actors->fromRequest($request), $topicId, $id, $studentId));

        return new JsonResponse(['data' => array_map($this->wrongOf(...), $views)]);
    }

    public function studentAttempt(Request $request, string $id, string $studentId, string $attemptId, GetStudentAttemptHandler $handler): JsonResponse
    {
        $view = $handler->handle(new GetStudentAttemptQuery($this->actors->fromRequest($request), $id, $studentId, $attemptId));

        return new JsonResponse(['data' => $this->presenter->attempt($view)]);
    }

    public function classroomSummary(Request $request, string $id, string $topicId, GetQuestionSummaryHandler $handler): JsonResponse
    {
        return new JsonResponse(['data' => $this->summaryOf($handler->handle(new GetQuestionSummaryQuery($this->actors->fromRequest($request), $topicId, $id)))]);
    }

    public function subjectSummary(Request $request, string $topicId, GetQuestionSummaryHandler $handler): JsonResponse
    {
        return new JsonResponse(['data' => $this->summaryOf($handler->handle(new GetQuestionSummaryQuery($this->actors->fromRequest($request), $topicId)))]);
    }

    /** @return array<string, mixed> */
    private function summaryOf(QuestionSummaryView $view): array
    {
        return [
            'students' => $view->students,
            'questions' => array_map(static fn (QuestionStats $q): array => [
                'question_id' => $q->questionId,
                'type' => $q->type,
                'statement' => $q->statement,
                'answered' => $q->answered,
                'wrong' => $q->wrong,
                'wrong_percent' => $q->wrongPercent(),
                'correct_option_id' => $q->correctOptionId,
                'options' => array_map(static fn (OptionCount $o): array => ['id' => $o->id, 'text' => $o->text, 'chosen' => $o->chosen], $q->options),
                'other_chosen' => $q->otherChosen,
            ], $view->questions),
        ];
    }

    /** @return array{topic_id: string, points: int, tier: string, next_tier: array{tier: string, points: int}|null} */
    private function topicProgress(TopicProgressView $view): array
    {
        return ['topic_id' => $view->topicId, 'points' => $view->points, 'tier' => $view->tier->value, 'next_tier' => $this->nextTier($view->nextTier)];
    }

    /** @return array{tier: string, points: int}|null */
    private function nextTier(?Tier $tier): ?array
    {
        return $tier === null ? null : ['tier' => $tier->value, 'points' => $tier->threshold()];
    }

    /** @return array<string, mixed> */
    private function historyOf(TopicHistoryView $view): array
    {
        return [
            'points' => $view->points,
            'tier' => $view->tier->value,
            'next_tier' => $this->nextTier($view->nextTier),
            'attempts' => array_map(static fn (AttemptSummaryView $a): array => [
                'id' => $a->id,
                'started_at' => $a->startedAt,
                'completed_at' => $a->completedAt,
                'total' => $a->total,
                'answered' => $a->answered,
                'correct' => $a->correct,
                'points_before' => $a->pointsBefore,
                'points_after' => $a->pointsAfter,
                'points_change' => $a->pointsChange(),
                'tier_before' => $a->tierBefore->value,
                'tier_after' => $a->tierAfter->value,
            ], $view->attempts),
        ];
    }

    /** @return array<string, mixed> */
    private function wrongOf(WrongQuestionView $view): array
    {
        return [
            'question_id' => $view->questionId,
            'type' => $view->type,
            'statement' => $view->statement,
            'options' => array_map(static fn (SnapshotOption $o): array => ['id' => $o->id, 'text' => $o->text], $view->options),
            'chosen_option_id' => $view->chosenOptionId,
            'correct_option_id' => $view->correctOptionId,
            'explanation' => $view->explanation,
            'answered_at' => $view->answeredAt,
        ];
    }
}
