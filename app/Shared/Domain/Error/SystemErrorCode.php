<?php

declare(strict_types=1);

namespace App\Shared\Domain\Error;

enum SystemErrorCode: string implements ErrorCode
{
    case UnexpectedError = 'system.unexpected_error';
    case IdempotencyConflict = 'system.idempotency_conflict';
    case ValidationFailed = 'validation.failed';
    case ValidationInvalid = 'validation.invalid';
    case Unauthenticated = 'auth.unauthenticated';
    case Forbidden = 'auth.forbidden';
    case NotFound = 'http.not_found';
    case MethodNotAllowed = 'http.method_not_allowed';
    case TooManyRequests = 'http.too_many_requests';
    case HttpError = 'http.error';

    public function code(): string
    {
        return $this->value;
    }
}
