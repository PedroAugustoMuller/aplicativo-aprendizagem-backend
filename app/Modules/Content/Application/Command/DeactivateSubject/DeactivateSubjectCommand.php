<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Command\DeactivateSubject;

use App\Shared\Domain\Auth\Actor;

final readonly class DeactivateSubjectCommand
{
    public function __construct(
        public Actor $actor,
        public string $id,
    ) {}
}
