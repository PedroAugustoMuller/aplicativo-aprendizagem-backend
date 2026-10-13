<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Exception;

use App\Modules\Identity\Domain\Error\IdentityErrorCode;
use App\Shared\Domain\Exception\BusinessRuleException;

/** A teacher of the whole classroom was also assigned to one of its subjects. */
final class ClassroomTeacherAssignmentRedundantException extends BusinessRuleException
{
    public function errorCode(): string
    {
        return IdentityErrorCode::ClassroomTeacherAssignmentRedundant->value;
    }

    public function params(): array
    {
        return [];
    }
}
