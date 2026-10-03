<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Application\DTO;

use App\Modules\Quiz\Domain\Entity\AttemptQuestion;
use App\Modules\Quiz\Domain\ValueObject\AnswerResult;
use App\Modules\Quiz\Domain\ValueObject\SnapshotOption;

/** What the student may see of one question: never the correct option before answering. */
final readonly class AttemptQuestionView
{
    /** @param list<SnapshotOption> $options */
    public function __construct(
        public string $id,
        public int $position,
        public string $type,
        public string $statement,
        public array $options,
        public ?AnswerResult $result,
    ) {}

    public static function of(AttemptQuestion $question): self
    {
        return new self(
            $question->id()->value(),
            $question->position(),
            $question->type(),
            $question->statement(),
            $question->options(),
            $question->result(),
        );
    }
}
