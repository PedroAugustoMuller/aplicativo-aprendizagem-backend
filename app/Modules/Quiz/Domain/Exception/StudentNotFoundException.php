<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Domain\Exception;

use App\Modules\Quiz\Domain\Error\QuizErrorCode;
use App\Shared\Domain\Exception\NotFoundException;

/** A student unknown to, or not enrolled in, the classroom asked about. */
final class StudentNotFoundException extends NotFoundException
{
    public function errorCode(): string
    {
        return QuizErrorCode::StudentNotFound->value;
    }

    public function params(): array
    {
        return [];
    }
}
