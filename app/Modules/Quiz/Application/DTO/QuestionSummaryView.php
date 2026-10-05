<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Application\DTO;

use App\Modules\Quiz\Domain\ValueObject\QuestionStats;

final readonly class QuestionSummaryView
{
    /**
     * @param  int  $students  distinct students in scope, answered or not
     * @param  list<QuestionStats>  $questions
     */
    public function __construct(
        public int $students,
        public array $questions,
    ) {}
}
