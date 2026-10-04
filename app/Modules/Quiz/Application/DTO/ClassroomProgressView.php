<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Application\DTO;

final readonly class ClassroomProgressView
{
    /** @param list<StudentProgressView> $students sorted by name */
    public function __construct(public array $students) {}
}
