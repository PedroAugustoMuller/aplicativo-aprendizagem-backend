<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\Exception;

use App\Modules\Content\Domain\Error\ContentErrorCode;
use App\Shared\Domain\Exception\ConflictException;

/** The question changed after this editor loaded it; nothing was written. */
final class QuestionEditedElsewhereException extends ConflictException
{
    public function errorCode(): string
    {
        return ContentErrorCode::QuestionEditedElsewhere->value;
    }

    public function params(): array
    {
        return [];
    }
}
