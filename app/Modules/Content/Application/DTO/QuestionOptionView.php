<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\DTO;

final readonly class QuestionOptionView
{
    public function __construct(
        public string $id,
        public string $text,
        public bool $correct,
        public int $position,
    ) {}
}
