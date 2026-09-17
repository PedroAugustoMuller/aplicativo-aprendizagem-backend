<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\Repository;

use App\Modules\Content\Domain\Entity\Topic;

interface TopicRepository
{
    public function save(Topic $topic): void;
}
