<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Application\DTO;

use App\Modules\Quiz\Domain\ValueObject\AnswerResult;
use App\Modules\Quiz\Domain\ValueObject\Score;

final readonly class AnswerOutcome
{
    public function __construct(
        public AnswerResult $result,
        public Score $score,
        public bool $completed,
    ) {}
}
