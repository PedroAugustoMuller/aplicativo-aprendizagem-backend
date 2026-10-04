<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Application\Query\GetClassroomProgress;

use App\Modules\Quiz\Application\DTO\ClassroomProgressView;
use App\Modules\Quiz\Application\DTO\StudentProgressView;
use App\Modules\Quiz\Application\DTO\TopicProgressView;
use App\Modules\Quiz\Application\Port\ProgressReader;
use App\Modules\Quiz\Application\Service\ProgressAccess;
use App\Modules\Quiz\Domain\Service\Progress;
use App\Shared\Domain\Contract\RosterStudent;

/** Every enrolled student and their tier per topic, within the classroom's subject only. */
final readonly class GetClassroomProgressHandler
{
    public function __construct(private ProgressReader $reader, private ProgressAccess $access) {}

    public function handle(GetClassroomProgressQuery $query): ClassroomProgressView
    {
        $classroom = $this->access->classroom($query->actor, $query->classroomId);
        $answers = $this->reader->answersForStudents(
            array_map(static fn (RosterStudent $s): string => $s->id, $classroom->students),
            $classroom->subjectId,
        );

        return new ClassroomProgressView(array_map(static function (RosterStudent $student) use ($answers): StudentProgressView {
            $topics = [];

            foreach ($answers[$student->id] ?? [] as $topicId => $topicAnswers) {
                $topics[] = TopicProgressView::of((string) $topicId, Progress::fromAnswers($topicAnswers));
            }

            return new StudentProgressView($student->id, $student->name, $student->username, $topics);
        }, $classroom->students));
    }
}
