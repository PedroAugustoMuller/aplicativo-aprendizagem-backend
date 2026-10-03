<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Quiz\Support;

use App\Shared\Domain\Contract\BankQuestion;
use App\Shared\Domain\Contract\BankTopic;
use App\Shared\Domain\Contract\QuestionBank;

final class FakeQuestionBank implements QuestionBank
{
    /** @param list<BankQuestion> $questions */
    public function __construct(public ?BankTopic $topic, public array $questions) {}

    public function topic(string $topicId): ?BankTopic
    {
        return $this->topic?->id === $topicId ? $this->topic : null;
    }

    public function activeQuestions(string $topicId): array
    {
        return $this->topic?->id === $topicId ? $this->questions : [];
    }
}
