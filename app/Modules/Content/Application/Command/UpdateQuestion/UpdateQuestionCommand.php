<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Command\UpdateQuestion;

use App\Modules\Content\Domain\ValueObject\OptionDraft;
use App\Shared\Domain\Auth\Actor;

final readonly class UpdateQuestionCommand
{
    /** @param list<OptionDraft>|null $options */
    public function __construct(
        public Actor $actor,
        public string $id,
        public int $version,
        public string $statement,
        public ?string $explanation,
        public ?array $options,
        public ?bool $answer,
    ) {}
}
