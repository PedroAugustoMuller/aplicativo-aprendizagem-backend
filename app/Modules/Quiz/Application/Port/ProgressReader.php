<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Application\Port;

use App\Modules\Quiz\Application\DTO\AnsweredQuestionRow;
use App\Modules\Quiz\Application\DTO\AttemptSummaryRow;
use App\Modules\Quiz\Domain\ValueObject\ScoredAnswer;
use App\Modules\Quiz\Domain\ValueObject\SummaryAnswer;

/** Read side of scoring: narrow selects straight into DTOs, never aggregates. A null subject = any subject. */
interface ProgressReader
{
    /** @return list<ScoredAnswer> one student's graded answers in one topic */
    public function answersForTopic(string $studentId, string $topicId, ?string $subjectId): array;

    /** @return array<string, list<ScoredAnswer>> graded answers keyed by topic id */
    public function answersBySubject(string $studentId, string $subjectId): array;

    /**
     * @param  list<string>  $studentIds
     * @return array<string, array<string, list<ScoredAnswer>>> student id → topic id → answers
     */
    public function answersForStudents(array $studentIds, string $subjectId): array;

    /** @return list<AttemptSummaryRow> newest first */
    public function attemptsForTopic(string $studentId, string $topicId, ?string $subjectId): array;

    /** @return list<AnsweredQuestionRow> answered rows that still link to a bank question */
    public function answeredQuestions(string $studentId, string $topicId, ?string $subjectId): array;

    /**
     * @param  list<string>  $studentIds
     * @return list<SummaryAnswer> the students' graded answers in the topic that still link to a bank question
     */
    public function summaryAnswers(array $studentIds, string $topicId, string $subjectId): array;
}
