<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Query\ListClassrooms;

use App\Shared\Domain\Auth\Actor;

final readonly class ListClassroomsQuery
{
    public function __construct(public Actor $actor) {}
}
