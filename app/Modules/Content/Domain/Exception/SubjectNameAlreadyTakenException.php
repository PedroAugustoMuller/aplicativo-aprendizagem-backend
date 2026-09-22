<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\Exception;

use App\Modules\Content\Domain\Error\ContentErrorCode;
use App\Shared\Domain\Exception\ConflictException;

final class SubjectNameAlreadyTakenException extends ConflictException
{
    public function __construct(private readonly string $name)
    {
        parent::__construct();
    }

    public function errorCode(): string
    {
        return ContentErrorCode::SubjectNameAlreadyTaken->value;
    }

    public function params(): array
    {
        return ['name' => $this->name];
    }
}
