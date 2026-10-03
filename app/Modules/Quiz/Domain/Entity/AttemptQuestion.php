<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Domain\Entity;

use App\Modules\Quiz\Domain\Exception\InvalidAnswerOptionException;
use App\Modules\Quiz\Domain\Service\Shuffler;
use App\Modules\Quiz\Domain\ValueObject\AnswerId;
use App\Modules\Quiz\Domain\ValueObject\AnswerResult;
use App\Modules\Quiz\Domain\ValueObject\AttemptQuestionId;
use App\Modules\Quiz\Domain\ValueObject\SnapshotOption;
use App\Shared\Domain\Contract\BankQuestion;
use DateTimeImmutable;
use LogicException;

/**
 * Part of the Attempt aggregate: a frozen copy of one bank question plus, once
 * answered, the answer. Grading reads only this copy, so a later edit of the bank
 * never changes what the student saw or how it was graded.
 */
final class AttemptQuestion
{
    /** @param list<SnapshotOption> $options in the order the student sees them */
    public function __construct(
        private readonly AttemptQuestionId $id,
        private readonly int $position,
        private readonly ?string $questionId,
        private readonly string $type,
        private readonly string $statement,
        private readonly ?string $explanation,
        private readonly array $options,
        private readonly string $correctOptionId,
        private ?AnswerId $answerId = null,
        private ?string $chosenOptionId = null,
        private ?DateTimeImmutable $answeredAt = null,
    ) {}

    public static function snapshot(AttemptQuestionId $id, int $position, BankQuestion $question, Shuffler $shuffler): self
    {
        // "Verdadeiro" before "Falso" is part of how the question reads; only choices are shuffled.
        $source = $question->type === 'true_false' ? $question->options : $shuffler->shuffle($question->options);
        $options = [];
        $correct = null;

        foreach ($source as $option) {
            $options[] = new SnapshotOption($option->id, $option->text);

            if ($option->correct) {
                $correct = $option->id;
            }
        }

        if ($correct === null) {
            throw new LogicException('A bank question always has exactly one correct option.');
        }

        return new self($id, $position, $question->id, $question->type, $question->statement, $question->explanation, $options, $correct);
    }

    /** Called by Attempt only, after it has ruled out a repeated or conflicting answer. */
    public function answer(AnswerId $answerId, string $optionId, DateTimeImmutable $at): AnswerResult
    {
        if (! $this->hasOption($optionId)) {
            throw new InvalidAnswerOptionException;
        }

        $this->answerId = $answerId;
        $this->chosenOptionId = $optionId;
        $this->answeredAt = $at;

        return $this->result() ?? throw new LogicException('An answered question has a result.');
    }

    public function result(): ?AnswerResult
    {
        if ($this->chosenOptionId === null) {
            return null;
        }

        return new AnswerResult(
            $this->id->value(),
            $this->chosenOptionId,
            $this->chosenOptionId === $this->correctOptionId,
            $this->correctOptionId,
            $this->explanation,
        );
    }

    public function isCorrect(): ?bool
    {
        return $this->chosenOptionId === null ? null : $this->chosenOptionId === $this->correctOptionId;
    }

    private function hasOption(string $optionId): bool
    {
        foreach ($this->options as $option) {
            if ($option->id === $optionId) {
                return true;
            }
        }

        return false;
    }

    public function id(): AttemptQuestionId
    {
        return $this->id;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function questionId(): ?string
    {
        return $this->questionId;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function statement(): string
    {
        return $this->statement;
    }

    public function explanation(): ?string
    {
        return $this->explanation;
    }

    /** @return list<SnapshotOption> */
    public function options(): array
    {
        return $this->options;
    }

    public function correctOptionId(): string
    {
        return $this->correctOptionId;
    }

    public function answerId(): ?AnswerId
    {
        return $this->answerId;
    }

    public function chosenOptionId(): ?string
    {
        return $this->chosenOptionId;
    }

    public function answeredAt(): ?DateTimeImmutable
    {
        return $this->answeredAt;
    }
}
