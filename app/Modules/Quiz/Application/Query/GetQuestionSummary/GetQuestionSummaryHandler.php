<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Application\Query\GetQuestionSummary;

use App\Modules\Quiz\Application\DTO\QuestionSummaryView;
use App\Modules\Quiz\Application\Port\ProgressReader;
use App\Modules\Quiz\Application\Service\ProgressAccess;
use App\Modules\Quiz\Domain\Exception\QuizTopicNotFoundException;
use App\Modules\Quiz\Domain\Service\QuestionSummary;
use App\Shared\Domain\Contract\QuestionBank;
use App\Shared\Domain\Contract\RosterStudent;

/** Which questions of a topic a classroom (or all of the actor's classrooms of its subject) gets wrong. */
final readonly class GetQuestionSummaryHandler
{
    public function __construct(private ProgressReader $reader, private ProgressAccess $access, private QuestionBank $bank) {}

    public function handle(GetQuestionSummaryQuery $query): QuestionSummaryView
    {
        if ($query->classroomId !== null) {
            // The classroom first: someone who may not read it learns nothing about the topic.
            $classroom = $this->access->classroom($query->actor, $query->classroomId);
            $topic = $this->bank->topic($query->topicId);

            if ($topic === null || $topic->subjectId !== $classroom->subjectId) {
                throw new QuizTopicNotFoundException;
            }

            $studentIds = array_map(static fn (RosterStudent $s): string => $s->id, $classroom->students);
        } else {
            // Only staff learn whether a topic exists, as on the classroom route.
            $this->access->staff($query->actor);
            $topic = $this->bank->topic($query->topicId) ?? throw new QuizTopicNotFoundException;
            $studentIds = $this->access->subjectStudents($query->actor, $topic->subjectId);
        }

        return new QuestionSummaryView(
            count($studentIds),
            QuestionSummary::fromAnswers($this->reader->summaryAnswers($studentIds, $topic->id, $topic->subjectId)),
        );
    }
}
