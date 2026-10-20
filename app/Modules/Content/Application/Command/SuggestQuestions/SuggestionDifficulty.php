<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Command\SuggestQuestions;

enum SuggestionDifficulty: string
{
    case Easy = 'easy';
    case Medium = 'medium';
    case Hard = 'hard';
}
