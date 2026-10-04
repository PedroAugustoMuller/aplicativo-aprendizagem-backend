<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Application\DTO;

use App\Modules\Quiz\Domain\ValueObject\SnapshotOption;
use DateTimeInterface;

final readonly class WrongQuestionView
{
    /** @param list<SnapshotOption> $options */
    public function __construct(
        public string $questionId,
        public string $type,
        public string $statement,
        public array $options,
        public string $chosenOptionId,
        public string $correctOptionId,
        public ?string $explanation,
        public string $answeredAt,
    ) {}

    public static function of(AnsweredQuestionRow $row): self
    {
        return new self(
            $row->questionId, $row->type, $row->statement, $row->options, $row->chosenOptionId,
            $row->correctOptionId, $row->explanation, $row->answeredAt->format(DateTimeInterface::ATOM),
        );
    }
}
