<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Domain\Exception;

use App\Modules\Quiz\Domain\Error\QuizErrorCode;
use App\Shared\Domain\Exception\NotFoundException;

/** A classroom id nobody has. Quiz's own code: it may not reuse Identity's. */
final class ClassroomNotFoundException extends NotFoundException
{
    public function errorCode(): string
    {
        return QuizErrorCode::ClassroomNotFound->value;
    }

    public function params(): array
    {
        return [];
    }
}
