<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Contract;

use App\Modules\Content\Infrastructure\Persistence\SubjectModel;
use App\Shared\Domain\Contract\SubjectCatalog;
use Illuminate\Support\Str;

/**
 * Implements Shared's SubjectCatalog contract on top of Content's own persistence,
 * so other modules can validate a subject reference without importing Content's
 * internals.
 */
final class EloquentSubjectCatalog implements SubjectCatalog
{
    public function isActiveSubject(string $subjectId): bool
    {
        // Postgres rejects a malformed uuid literal with an exception rather than
        // simply finding no rows, so a non-UUID string must be rejected before it
        // ever reaches the query.
        if (! Str::isUuid($subjectId)) {
            return false;
        }

        return SubjectModel::query()->whereKey($subjectId)->whereNull('deactivated_at')->exists();
    }
}
