<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\Exception;

use App\Modules\Content\Domain\Error\ContentErrorCode;
use App\Shared\Domain\Exception\BusinessRuleException;

/**
 * One code for every option rule; `reason` (count, correct, duplicate, empty,
 * unknown_option, type) says which, for logs and a future finer message.
 */
final class InvalidQuestionOptionsException extends BusinessRuleException
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
        return ContentErrorCode::QuestionInvalidOptions->value;
    }

    public function params(): array
    {
        return ['reason' => $this->reason];
    }
}
