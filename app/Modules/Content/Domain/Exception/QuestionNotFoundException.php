<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\Exception;

use App\Modules\Content\Domain\Error\ContentErrorCode;
use App\Shared\Domain\Exception\NotFoundException;

final class QuestionNotFoundException extends NotFoundException
{
    public function errorCode(): string
    {
        return ContentErrorCode::QuestionNotFound->value;
    }

    public function params(): array
    {
        return [];
    }
}
