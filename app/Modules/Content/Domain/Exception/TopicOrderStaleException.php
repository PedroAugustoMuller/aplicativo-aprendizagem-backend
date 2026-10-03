<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\Exception;

use App\Modules\Content\Domain\Error\ContentErrorCode;
use App\Shared\Domain\Exception\ConflictException;

/** The order sent is not exactly the subject's current topics: someone else changed them. */
final class TopicOrderStaleException extends ConflictException
{
    public function errorCode(): string
    {
        return ContentErrorCode::TopicOrderStale->value;
    }

    public function params(): array
    {
        return [];
    }
}
