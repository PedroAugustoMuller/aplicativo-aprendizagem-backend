<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Application\Query\GetWrongQuestions;

use App\Shared\Domain\Auth\Actor;

/** Without a classroom: the actor's own. With one: that classroom's student (staff). */
final readonly class GetWrongQuestionsQuery
{
    public function __construct(
        public Actor $actor,
        public string $topicId,
        public ?string $classroomId = null,
        public ?string $studentId = null,
    ) {}
}
