<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\Exception;

use App\Modules\Content\Domain\Error\ContentErrorCode;
use App\Shared\Domain\Exception\ServiceUnavailableException;

/**
 * The provider timed out, refused (quota, 5xx) or answered something unreadable.
 * `reason` is for logs only; the client gets one code and a "try again".
 */
final class SuggestionsUnavailableException extends ServiceUnavailableException
{
    public function __construct(private readonly string $reason)
    {
        parent::__construct();
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function errorCode(): string
    {
        return ContentErrorCode::SuggestionsUnavailable->value;
    }

    public function params(): array
    {
        return [];
    }
}
