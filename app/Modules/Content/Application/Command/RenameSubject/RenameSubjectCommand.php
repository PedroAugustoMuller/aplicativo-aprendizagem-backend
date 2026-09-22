<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Command\RenameSubject;

use App\Shared\Domain\Auth\Actor;

final readonly class RenameSubjectCommand
{
    public function __construct(
        public Actor $actor,
        public string $id,
        public string $name,
    ) {}
}
