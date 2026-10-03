<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Domain\Exception;

use App\Modules\Quiz\Domain\Error\QuizErrorCode;
use App\Shared\Domain\Exception\ConflictException;

final class TopicHasNoQuestionsException extends ConflictException
{
    public function errorCode(): string
    {
        return QuizErrorCode::TopicHasNoQuestions->value;
    }

    public function params(): array
    {
        return [];
    }
}
