<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Query\ListTopics;

use App\Shared\Domain\Auth\Actor;

final readonly class ListTopicsQuery
{
    public function __construct(public Actor $actor, public string $subjectId) {}
}
