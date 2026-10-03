<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Command\ReorderTopics;

use App\Shared\Domain\Auth\Actor;

final readonly class ReorderTopicsCommand
{
    /** @param list<string> $ids every topic of the subject, in the new order */
    public function __construct(
        public Actor $actor,
        public string $subjectId,
        public array $ids,
    ) {}
}
