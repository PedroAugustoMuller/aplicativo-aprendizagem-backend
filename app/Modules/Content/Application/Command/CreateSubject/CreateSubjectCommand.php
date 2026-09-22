<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Command\CreateSubject;

use App\Shared\Domain\Auth\Actor;

final readonly class CreateSubjectCommand
{
    public function __construct(
        public Actor $actor,
        public string $id,
        public string $name,
    ) {}
}
