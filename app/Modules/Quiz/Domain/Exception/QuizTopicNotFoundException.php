<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Domain\Exception;

use App\Modules\Quiz\Domain\Error\QuizErrorCode;
use App\Shared\Domain\Exception\NotFoundException;

final class QuizTopicNotFoundException extends NotFoundException
{
    public function errorCode(): string
    {
        return QuizErrorCode::TopicNotFound->value;
    }

    public function params(): array
    {
        return [];
    }
}
