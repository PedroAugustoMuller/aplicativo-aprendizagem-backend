<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Query\SearchStudents;

use App\Shared\Domain\Auth\Actor;

final readonly class SearchStudentsQuery
{
    public function __construct(
        public Actor $actor,
        public string $text,
    ) {}
}
