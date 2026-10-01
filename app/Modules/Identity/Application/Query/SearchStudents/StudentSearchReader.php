<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Query\SearchStudents;

interface StudentSearchReader
{
    /**
     * Active students whose name or username contains $text (case-insensitive,
     * wildcards literal), ordered by name.
     *
     * @return list<StudentSearchResult>
     */
    public function search(string $text, int $limit): array;
}
