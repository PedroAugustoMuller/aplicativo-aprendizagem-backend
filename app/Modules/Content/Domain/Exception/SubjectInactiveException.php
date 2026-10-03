<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\Exception;

use App\Modules\Content\Domain\Error\ContentErrorCode;
use App\Shared\Domain\Exception\ConflictException;

/** A deactivated subject's content is read-only until an admin reactivates the subject. */
final class SubjectInactiveException extends ConflictException
{
    public function errorCode(): string
    {
        return ContentErrorCode::SubjectInactive->value;
    }

    public function params(): array
    {
        return [];
    }
}
