<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Query\ListTopics;

interface TopicListReader
{
    /** @return list<TopicListItem> */
    public function forSubject(string $subjectId): array;
}
