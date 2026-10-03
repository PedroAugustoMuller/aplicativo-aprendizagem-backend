<?php

declare(strict_types=1);

namespace App\Shared\Domain\Contract;

/**
 * Lives in Shared because Content owns topics and questions while Quiz (and later
 * Scoring) draws from them, and a module never imports another module's internals.
 */
interface QuestionBank
{
    /** Null when no topic has this id. */
    public function topic(string $topicId): ?BankTopic;

    /**
     * The topic's active questions, oldest first, each with its options in position order.
     *
     * @return list<BankQuestion>
     */
    public function activeQuestions(string $topicId): array;
}
