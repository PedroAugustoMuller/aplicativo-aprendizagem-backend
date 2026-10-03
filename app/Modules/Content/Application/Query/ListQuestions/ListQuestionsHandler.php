<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Query\ListQuestions;

use App\Modules\Content\Application\DTO\QuestionView;
use App\Modules\Content\Application\Service\AuthoringGate;
use App\Modules\Content\Domain\Exception\TopicNotFoundException;
use App\Modules\Content\Domain\Repository\TopicRepository;
use App\Modules\Content\Domain\ValueObject\TopicId;

final readonly class ListQuestionsHandler
{
    public function __construct(
        private TopicRepository $topics,
        private AuthoringGate $gate,
        private QuestionBankReader $reader,
    ) {}

    /** @return list<QuestionView> */
    public function handle(ListQuestionsQuery $query): array
    {
        $topic = $this->topics->findById(new TopicId($query->topicId)) ?? throw new TopicNotFoundException;
        $this->gate->assertCanRead($query->actor, $topic->subjectId());

        return $this->reader->forTopic($query->topicId);
    }
}
