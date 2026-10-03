<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Domain\Exception;

use App\Modules\Quiz\Domain\Error\QuizErrorCode;
use App\Shared\Domain\Exception\ConflictException;

/** The topic or its subject was deactivated: no new quiz starts on it. */
final class QuizTopicUnavailableException extends ConflictException
{
    public function errorCode(): string
    {
        return QuizErrorCode::TopicUnavailable->value;
    }

    public function params(): array
    {
        return [];
    }
}
