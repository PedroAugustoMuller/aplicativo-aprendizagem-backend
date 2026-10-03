<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Application\Command\StartAttempt;

use App\Modules\Quiz\Application\DTO\AttemptView;

/** `created` is false when an open (or the same, resent) attempt was returned instead. */
final readonly class StartedAttempt
{
    public function __construct(
        public AttemptView $view,
        public bool $created,
    ) {}
}
