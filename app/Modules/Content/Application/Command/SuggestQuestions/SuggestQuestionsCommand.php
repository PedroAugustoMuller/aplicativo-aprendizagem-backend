<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Command\SuggestQuestions;

use App\Shared\Domain\Auth\Actor;

final readonly class SuggestQuestionsCommand
{
    /** @param list<string> $types */
    public function __construct(
        public Actor $actor,
        public string $topicId,
        public int $count,
        public array $types,
        public string $difficulty,
        public ?string $notes,
        public ?string $sourceText,
    ) {}
}
