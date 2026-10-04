<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Infrastructure\Persistence;

use App\Modules\Quiz\Application\DTO\AnsweredQuestionRow;
use App\Modules\Quiz\Application\DTO\AttemptSummaryRow;
use App\Modules\Quiz\Application\Port\ProgressReader;
use App\Modules\Quiz\Domain\ValueObject\ScoredAnswer;
use App\Shared\Infrastructure\Persistence\EloquentAttribute;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** One query per call: scoring folds in PHP, so it only needs the narrow graded rows. */
final class EloquentProgressReader implements ProgressReader
{
    public function __construct(private readonly AttemptMapper $mapper) {}

    public function answersForTopic(string $studentId, string $topicId, ?string $subjectId): array
    {
        $rows = $this->graded($subjectId)
            ->where('a.student_id', $studentId)
            ->where('a.topic_id', $topicId)
            ->get();

        return array_values(array_map($this->scored(...), $rows->all()));
    }

    public function answersBySubject(string $studentId, string $subjectId): array
    {
        $grouped = [];

        foreach ($this->graded($subjectId)->where('a.student_id', $studentId)->get() as $row) {
            $grouped[EloquentAttribute::string($row->topic_id, 'quiz_attempts.topic_id')][] = $this->scored($row);
        }

        return $grouped;
    }

    public function answersForStudents(array $studentIds, string $subjectId): array
    {
        if ($studentIds === []) {
            return [];
        }

        $grouped = [];

        foreach ($this->graded($subjectId)->whereIn('a.student_id', $studentIds)->get() as $row) {
            $student = EloquentAttribute::string($row->student_id, 'quiz_attempts.student_id');
            $topic = EloquentAttribute::string($row->topic_id, 'quiz_attempts.topic_id');
            $grouped[$student][$topic][] = $this->scored($row);
        }

        return $grouped;
    }

    public function attemptsForTopic(string $studentId, string $topicId, ?string $subjectId): array
    {
        $query = DB::table('quiz_attempts as a')
            ->leftJoin('quiz_attempt_questions as q', 'q.attempt_id', '=', 'a.id')
            ->where('a.student_id', $studentId)
            ->where('a.topic_id', $topicId)
            ->groupBy('a.id', 'a.started_at', 'a.completed_at')
            ->orderByDesc('a.started_at')
            ->select(['a.id', 'a.started_at', 'a.completed_at'])
            ->selectRaw('count(q.id) as total')
            ->selectRaw('count(q.is_correct) as answered')
            ->selectRaw('count(*) filter (where q.is_correct) as correct');

        if ($subjectId !== null) {
            $query->where('a.subject_id', $subjectId);
        }

        $summaries = [];

        foreach ($query->get() as $row) {
            $summaries[] = new AttemptSummaryRow(
                EloquentAttribute::string($row->id, 'quiz_attempts.id'),
                $this->atom($row->started_at),
                $row->completed_at === null ? null : $this->atom($row->completed_at),
                EloquentAttribute::int($row->total, 'total'),
                EloquentAttribute::int($row->answered, 'answered'),
                EloquentAttribute::int($row->correct, 'correct'),
            );
        }

        return $summaries;
    }

    public function answeredQuestions(string $studentId, string $topicId, ?string $subjectId): array
    {
        $query = $this->graded($subjectId)
            ->where('a.student_id', $studentId)
            ->where('a.topic_id', $topicId)
            ->whereNotNull('q.question_id')
            ->addSelect(['q.question_id', 'q.type', 'q.statement', 'q.options', 'q.chosen_option_id', 'q.correct_option_id', 'q.explanation', 'a.started_at']);

        $answered = [];

        foreach ($query->get() as $row) {
            $answered[] = new AnsweredQuestionRow(
                EloquentAttribute::string($row->question_id, 'quiz_attempt_questions.question_id'),
                EloquentAttribute::string($row->type, 'quiz_attempt_questions.type'),
                EloquentAttribute::string($row->statement, 'quiz_attempt_questions.statement'),
                $this->mapper->options(json_decode(EloquentAttribute::string($row->options, 'quiz_attempt_questions.options'), true, flags: JSON_THROW_ON_ERROR)),
                EloquentAttribute::string($row->chosen_option_id, 'quiz_attempt_questions.chosen_option_id'),
                EloquentAttribute::string($row->correct_option_id, 'quiz_attempt_questions.correct_option_id'),
                $row->explanation === null ? null : EloquentAttribute::string($row->explanation, 'quiz_attempt_questions.explanation'),
                (bool) $row->is_correct,
                $this->time($row->answered_at),
                $this->time($row->started_at),
            );
        }

        return $answered;
    }

    private function graded(?string $subjectId): Builder
    {
        $query = DB::table('quiz_attempt_questions as q')
            ->join('quiz_attempts as a', 'a.id', '=', 'q.attempt_id')
            ->whereNotNull('q.is_correct')
            ->orderBy('q.answered_at')
            ->orderBy('q.position')
            ->select(['q.id', 'q.attempt_id', 'q.position', 'q.is_correct', 'q.answered_at', 'a.student_id', 'a.topic_id']);

        if ($subjectId !== null) {
            $query->where('a.subject_id', $subjectId);
        }

        return $query;
    }

    private function scored(object $row): ScoredAnswer
    {
        return new ScoredAnswer(
            EloquentAttribute::string($row->attempt_id ?? null, 'quiz_attempt_questions.attempt_id'),
            EloquentAttribute::string($row->id ?? null, 'quiz_attempt_questions.id'),
            EloquentAttribute::int($row->position ?? null, 'quiz_attempt_questions.position'),
            (bool) ($row->is_correct ?? false),
            $this->time($row->answered_at ?? null),
        );
    }

    /** Timestamps are stored without a zone, in the app's UTC. */
    private function time(mixed $value): DateTimeImmutable
    {
        return new DateTimeImmutable(EloquentAttribute::string($value, 'timestamp'), new DateTimeZone('UTC'));
    }

    private function atom(mixed $value): string
    {
        return $this->time($value)->format(DateTimeInterface::ATOM);
    }
}
