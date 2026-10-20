<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\Exception;

use App\Modules\Content\Domain\Error\ContentErrorCode;
use App\Shared\Domain\Exception\BusinessRuleException;

/** The provider answered, but none of its questions follows the bank's rules. */
final class NoValidSuggestionsException extends BusinessRuleException
{
    public function errorCode(): string
    {
        return ContentErrorCode::SuggestionsEmpty->value;
    }

    public function params(): array
    {
        return [];
    }
}
