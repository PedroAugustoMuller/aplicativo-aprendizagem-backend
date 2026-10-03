<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Command\SetQuestionActivation;

use App\Shared\Domain\Auth\Actor;

final readonly class SetQuestionActivationCommand
{
    public function __construct(public Actor $actor, public string $id, public bool $active) {}
}
