<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Application\Query\GetSubjectProgress;

use App\Shared\Domain\Auth\Actor;

final readonly class GetSubjectProgressQuery
{
    public function __construct(
        public Actor $actor,
        public string $subjectId,
    ) {}
}
