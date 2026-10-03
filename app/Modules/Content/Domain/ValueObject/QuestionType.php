<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\ValueObject;

enum QuestionType: string
{
    case MultipleChoice = 'multiple_choice';
    case TrueFalse = 'true_false';
}
