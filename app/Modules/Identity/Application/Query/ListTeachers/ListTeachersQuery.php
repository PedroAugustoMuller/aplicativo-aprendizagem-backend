<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Query\ListTeachers;

use App\Shared\Domain\Auth\Actor;

final readonly class ListTeachersQuery
{
    public function __construct(public Actor $actor) {}
}
