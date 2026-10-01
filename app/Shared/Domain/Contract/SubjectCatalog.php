<?php

declare(strict_types=1);

namespace App\Shared\Domain\Contract;

/**
 * Lives in Shared because both Content (who owns subjects) and other modules that
 * need to validate a subject reference (Quiz, Scoring) need it, and a module never
 * imports another module's internals.
 */
interface SubjectCatalog
{
    public function isActiveSubject(string $subjectId): bool;
}
