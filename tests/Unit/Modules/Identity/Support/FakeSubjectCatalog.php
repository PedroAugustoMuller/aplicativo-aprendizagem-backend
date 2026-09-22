<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity\Support;

use App\Shared\Domain\Contract\SubjectCatalog;

final class FakeSubjectCatalog implements SubjectCatalog
{
    /** @param  list<string>  $activeIds */
    public function __construct(private readonly array $activeIds) {}

    public function isActiveSubject(string $subjectId): bool
    {
        return in_array($subjectId, $this->activeIds, true);
    }
}
