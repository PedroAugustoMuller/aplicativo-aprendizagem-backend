<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Domain\Exception;

use App\Modules\Quiz\Domain\Error\QuizErrorCode;
use App\Shared\Domain\Exception\ConflictException;

/** Answers are final: a second, different answer to one question is refused. */
final class QuestionAlreadyAnsweredException extends ConflictException
{
    public function errorCode(): string
    {
        return QuizErrorCode::QuestionAlreadyAnswered->value;
    }

    public function params(): array
    {
        return [];
    }
}
