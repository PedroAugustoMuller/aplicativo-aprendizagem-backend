<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\Entity;

use App\Modules\Content\Domain\ValueObject\OptionText;
use App\Modules\Content\Domain\ValueObject\QuestionOptionId;

/** Part of the Question aggregate; replaced, never mutated. */
final readonly class QuestionOption
{
    public function __construct(
        public QuestionOptionId $id,
        public OptionText $text,
        public bool $correct,
        public int $position,
    ) {}
}
