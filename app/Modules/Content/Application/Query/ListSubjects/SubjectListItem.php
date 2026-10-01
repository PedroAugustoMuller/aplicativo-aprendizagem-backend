<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Query\ListSubjects;

/**
 * A read model. Not the Subject aggregate — reading a list must not hydrate aggregates.
 */
final readonly class SubjectListItem
{
    public function __construct(
        public string $id,
        public string $name,
        public bool $active,
    ) {}
}
