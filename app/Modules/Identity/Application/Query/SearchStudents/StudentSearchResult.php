<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Query\SearchStudents;

final readonly class StudentSearchResult
{
    /** @param list<array{id: string, name: string}> $classrooms the student's active classrooms */
    public function __construct(
        public string $id,
        public string $name,
        public string $login,
        public array $classrooms,
    ) {}
}
