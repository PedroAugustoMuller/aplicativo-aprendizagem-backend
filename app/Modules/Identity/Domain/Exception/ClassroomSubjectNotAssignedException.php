<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Exception;

use App\Modules\Identity\Domain\Error\IdentityErrorCode;
use App\Shared\Domain\Exception\BusinessRuleException;

/** A teacher was assigned to a subject the classroom does not hold. */
final class ClassroomSubjectNotAssignedException extends BusinessRuleException
{
    public function errorCode(): string
    {
        return IdentityErrorCode::ClassroomSubjectNotAssigned->value;
    }

    public function params(): array
    {
        return [];
    }
}
