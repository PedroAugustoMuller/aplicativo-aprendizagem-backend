<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\ValueObject;

/** An option as an author sent it: `id` is null for a new option. */
final readonly class OptionDraft
{
    public function __construct(
        public ?string $id,
        public string $text,
        public bool $correct,
    ) {}
}
