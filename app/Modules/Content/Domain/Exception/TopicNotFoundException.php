<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\Exception;

use App\Modules\Content\Domain\Error\ContentErrorCode;
use App\Shared\Domain\Exception\NotFoundException;

final class TopicNotFoundException extends NotFoundException
{
    public function errorCode(): string
    {
        return ContentErrorCode::TopicNotFound->value;
    }

    public function params(): array
    {
        return [];
    }
}
