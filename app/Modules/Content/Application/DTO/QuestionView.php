<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\DTO;

use App\Modules\Content\Domain\Entity\Question;
use App\Modules\Content\Domain\Entity\QuestionOption;

/** Includes which option is correct: only ever sent to the subject's authors. */
final readonly class QuestionView
{
    /** @param list<QuestionOptionView> $options */
    public function __construct(
        public string $id,
        public string $topicId,
        public string $type,
        public string $statement,
        public ?string $explanation,
        public bool $active,
        public int $version,
        public array $options,
    ) {}

    public static function of(Question $question): self
    {
        return new self(
            $question->id()->value(),
            $question->topicId()->value(),
            $question->type()->value,
            $question->statement()->value(),
            $question->explanation(),
            $question->isActive(),
            $question->version(),
            array_map(
                static fn (QuestionOption $o): QuestionOptionView => new QuestionOptionView($o->id->value(), $o->text->value(), $o->correct, $o->position),
                $question->options(),
            ),
        );
    }
}
