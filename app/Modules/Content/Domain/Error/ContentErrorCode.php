<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\Error;

use App\Shared\Domain\Error\ErrorCode;

enum ContentErrorCode: string implements ErrorCode
{
    case SubjectNotFound = 'content.subject_not_found';
    case SubjectNameAlreadyTaken = 'content.subject.name_already_taken';

    public function code(): string
    {
        return $this->value;
    }
}
