<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\Exception;

use App\Modules\Content\Domain\Error\ContentErrorCode;
use App\Shared\Domain\Exception\ServiceUnavailableException;

/** AI_PROVIDER is empty or unknown on this server. */
final class SuggestionsDisabledException extends ServiceUnavailableException
{
    public function errorCode(): string
    {
        return ContentErrorCode::SuggestionsDisabled->value;
    }

    public function params(): array
    {
        return [];
    }
}
