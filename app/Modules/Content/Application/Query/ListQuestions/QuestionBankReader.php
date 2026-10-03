<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Query\ListQuestions;

use App\Modules\Content\Application\DTO\QuestionView;

interface QuestionBankReader
{
    /** @return list<QuestionView> active and deactivated, oldest first */
    public function forTopic(string $topicId): array;
}
