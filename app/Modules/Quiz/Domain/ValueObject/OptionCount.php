<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Domain\ValueObject;

/** An option and how many students picked it in their latest answer. */
final readonly class OptionCount
{
    public function __construct(
        public string $id,
        public string $text,
        public int $chosen,
    ) {}
}
