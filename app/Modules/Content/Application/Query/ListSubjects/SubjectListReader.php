<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Query\ListSubjects;

interface SubjectListReader
{
    /**
     * @param  list<string>|null  $onlyIds  null means all subjects.
     * @return list<SubjectListItem>
     */
    public function list(?array $onlyIds): array;
}
