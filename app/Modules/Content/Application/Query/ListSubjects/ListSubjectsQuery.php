<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Query\ListSubjects;

use App\Shared\Domain\Auth\Actor;

final readonly class ListSubjectsQuery
{
    public function __construct(public Actor $actor) {}
}
