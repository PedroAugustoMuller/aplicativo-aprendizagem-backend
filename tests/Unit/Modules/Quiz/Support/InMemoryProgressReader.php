<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Quiz\Support;

use App\Modules\Quiz\Application\DTO\AnsweredQuestionRow;
use App\Modules\Quiz\Application\DTO\AttemptSummaryRow;
use App\Modules\Quiz\Application\Port\ProgressReader;
use App\Modules\Quiz\Domain\ValueObject\ScoredAnswer;

/** Rows are keyed "student|topic|subject"; a null subject filter matches any subject. */
final class InMemoryProgressReader implements ProgressReader
{
    /** @var array<string, list<ScoredAnswer>> */
    public array $answers = [];

    /** @var array<string, list<AttemptSummaryRow>> */
    public array $attempts = [];

    /** @var array<string, list<AnsweredQuestionRow>> */
    public array $questions = [];

    /** @var list<string> */
    public array $calls = [];

    /**
     * @template T
     *
     * @param  array<string, list<T>>  $rows
     * @return list<T>
     */
    private static function pick(array $rows, string $student, string $topic, ?string $subject): array
    {
        $found = [];

        foreach ($rows as $key => $list) {
            [$s, $t, $sub] = explode('|', $key);

            if ($s === $student && $t === $topic && ($subject === null || $subject === $sub)) {
                $found = [...$found, ...$list];
            }
        }

        return $found;
    }

    public function answersForTopic(string $studentId, string $topicId, ?string $subjectId): array
    {
        $this->calls[] = 'answersForTopic';

        return self::pick($this->answers, $studentId, $topicId, $subjectId);
    }

    public function answersBySubject(string $studentId, string $subjectId): array
    {
        $this->calls[] = 'answersBySubject';
        $grouped = [];

        foreach ($this->answers as $key => $list) {
            [$s, $t, $sub] = explode('|', $key);

            if ($s === $studentId && $sub === $subjectId) {
                $grouped[$t] = [...($grouped[$t] ?? []), ...$list];
            }
        }

        return $grouped;
    }

    public function answersForStudents(array $studentIds, string $subjectId): array
    {
        $this->calls[] = 'answersForStudents';
        $grouped = [];

        foreach ($this->answers as $key => $list) {
            [$s, $t, $sub] = explode('|', $key);

            if (in_array($s, $studentIds, true) && $sub === $subjectId) {
                $grouped[$s][$t] = [...($grouped[$s][$t] ?? []), ...$list];
            }
        }

        return $grouped;
    }

    public function attemptsForTopic(string $studentId, string $topicId, ?string $subjectId): array
    {
        return self::pick($this->attempts, $studentId, $topicId, $subjectId);
    }

    public function answeredQuestions(string $studentId, string $topicId, ?string $subjectId): array
    {
        return self::pick($this->questions, $studentId, $topicId, $subjectId);
    }
}
