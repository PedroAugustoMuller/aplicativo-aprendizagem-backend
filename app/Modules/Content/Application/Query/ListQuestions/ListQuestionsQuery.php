<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Query\ListQuestions;

use App\Shared\Domain\Auth\Actor;

final readonly class ListQuestionsQuery
{
    public function __construct(public Actor $actor, public string $topicId) {}
}
