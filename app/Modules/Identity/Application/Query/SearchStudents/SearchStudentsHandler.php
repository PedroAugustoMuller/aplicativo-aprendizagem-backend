<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Query\SearchStudents;

use App\Modules\Identity\Domain\Exception\AccessDeniedException;
use App\Modules\Identity\Domain\Policy\RosterPolicy;

final readonly class SearchStudentsHandler
{
    public const LIMIT = 20;

    public function __construct(
        private RosterPolicy $policy,
        private StudentSearchReader $reader,
    ) {}

    /** @return list<StudentSearchResult> */
    public function handle(SearchStudentsQuery $query): array
    {
        if (! $this->policy->canSearchStudents($query->actor)) {
            throw new AccessDeniedException;
        }

        return $this->reader->search(trim($query->text), self::LIMIT);
    }
}
