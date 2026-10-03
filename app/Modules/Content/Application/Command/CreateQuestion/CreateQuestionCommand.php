<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Command\CreateQuestion;

use App\Modules\Content\Domain\ValueObject\OptionDraft;
use App\Shared\Domain\Auth\Actor;

final readonly class CreateQuestionCommand
{
    /** @param list<OptionDraft>|null $options multiple choice only; $answer true/false only */
    public function __construct(
        public Actor $actor,
        public string $topicId,
        public string $id,
        public string $type,
        public string $statement,
        public ?string $explanation,
        public ?array $options,
        public ?bool $answer,
    ) {}
}
