<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Domain\Exception;

use App\Shared\Domain\Error\SystemErrorCode;
use App\Shared\Domain\Exception\ForbiddenException;

/** Quiz's twin of Content's ContentAccessDeniedException — a module never imports another module's internals. */
final class QuizAccessDeniedException extends ForbiddenException
{
    public function errorCode(): string
    {
        return SystemErrorCode::Forbidden->value;
    }

    public function params(): array
    {
        return [];
    }
}
