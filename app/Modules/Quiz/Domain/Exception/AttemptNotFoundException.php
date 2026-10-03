<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Domain\Exception;

use App\Modules\Quiz\Domain\Error\QuizErrorCode;
use App\Shared\Domain\Exception\NotFoundException;

/** Also thrown for another student's attempt: its existence is not this actor's business. */
final class AttemptNotFoundException extends NotFoundException
{
    public function errorCode(): string
    {
        return QuizErrorCode::AttemptNotFound->value;
    }

    public function params(): array
    {
        return [];
    }
}
