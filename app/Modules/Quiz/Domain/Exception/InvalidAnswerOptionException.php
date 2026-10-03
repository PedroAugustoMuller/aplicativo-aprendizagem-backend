<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Domain\Exception;

use App\Modules\Quiz\Domain\Error\QuizErrorCode;
use App\Shared\Domain\Exception\BusinessRuleException;

final class InvalidAnswerOptionException extends BusinessRuleException
{
    public function errorCode(): string
    {
        return QuizErrorCode::InvalidAnswerOption->value;
    }

    public function params(): array
    {
        return [];
    }
}
