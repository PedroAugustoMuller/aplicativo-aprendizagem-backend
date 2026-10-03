<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\Entity;

use App\Modules\Content\Domain\Exception\InvalidQuestionOptionsException;
use App\Modules\Content\Domain\Exception\QuestionEditedElsewhereException;
use App\Modules\Content\Domain\ValueObject\OptionDraft;
use App\Modules\Content\Domain\ValueObject\OptionText;
use App\Modules\Content\Domain\ValueObject\QuestionId;
use App\Modules\Content\Domain\ValueObject\QuestionOptionId;
use App\Modules\Content\Domain\ValueObject\QuestionStatement;
use App\Modules\Content\Domain\ValueObject\QuestionType;
use App\Modules\Content\Domain\ValueObject\TopicId;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * A question in a topic's bank. Edits are free and keep option ids stable
 * (answers will point at them); deactivating hides it from new quizzes and
 * never deletes it. `version` grows once per content edit: a save whose
 * loaded version no longer matches is refused (edited elsewhere).
 */
final class Question
{
    public const MIN_OPTIONS = 2;

    public const MAX_OPTIONS = 5;

    public const EXPLANATION_MAX_LENGTH = 1000;

    public const TRUE_TEXT = 'Verdadeiro';

    public const FALSE_TEXT = 'Falso';

    private bool $contentChanged = false;

    private bool $activationChanged = false;

    /** @var list<QuestionOptionId> */
    private array $removedOptionIds = [];

    private readonly int $loadedVersion;

    /** @param list<QuestionOption> $options */
    private function __construct(
        private readonly QuestionId $id,
        private readonly TopicId $topicId,
        private readonly QuestionType $type,
        private QuestionStatement $statement,
        private ?string $explanation,
        private array $options,
        private int $version,
        private ?DateTimeImmutable $deactivatedAt,
        private readonly bool $isNew,
    ) {
        $this->loadedVersion = $version;
    }

    /** @param list<OptionDraft> $drafts */
    public static function multipleChoice(QuestionId $id, TopicId $topicId, QuestionStatement $statement, ?string $explanation, array $drafts): self
    {
        return new self(
            $id, $topicId, QuestionType::MultipleChoice, $statement, self::cleanExplanation($explanation),
            self::multipleChoiceOptions($drafts, []), 1, null, true,
        );
    }

    public static function trueFalse(QuestionId $id, TopicId $topicId, QuestionStatement $statement, ?string $explanation, bool $answer): self
    {
        return new self($id, $topicId, QuestionType::TrueFalse, $statement, self::cleanExplanation($explanation), [
            new QuestionOption(QuestionOptionId::random(), new OptionText(self::TRUE_TEXT), $answer, 0),
            new QuestionOption(QuestionOptionId::random(), new OptionText(self::FALSE_TEXT), ! $answer, 1),
        ], 1, null, true);
    }

    /** @param list<QuestionOption> $options ordered by position */
    public static function restore(
        QuestionId $id,
        TopicId $topicId,
        QuestionType $type,
        QuestionStatement $statement,
        ?string $explanation,
        array $options,
        int $version,
        ?DateTimeImmutable $deactivatedAt,
    ): self {
        return new self($id, $topicId, $type, $statement, $explanation, $options, $version, $deactivatedAt, false);
    }

    /** @param list<OptionDraft> $drafts */
    public function editMultipleChoice(QuestionStatement $statement, ?string $explanation, array $drafts): void
    {
        if ($this->type !== QuestionType::MultipleChoice) {
            throw new InvalidQuestionOptionsException('type');
        }

        $current = [];

        foreach ($this->options as $option) {
            $current[$option->id->value()] = $option;
        }

        $options = self::multipleChoiceOptions($drafts, $current);
        $kept = [];

        foreach ($options as $option) {
            $kept[$option->id->value()] = true;
        }

        foreach ($current as $key => $option) {
            if (! isset($kept[$key])) {
                $this->removedOptionIds[] = $option->id;
            }
        }

        $this->applyEdit($statement, $explanation, $options);
    }

    public function editTrueFalse(QuestionStatement $statement, ?string $explanation, bool $answer): void
    {
        if ($this->type !== QuestionType::TrueFalse) {
            throw new InvalidQuestionOptionsException('type');
        }

        $options = array_map(
            static fn (QuestionOption $o): QuestionOption => new QuestionOption($o->id, $o->text, $o->position === 0 ? $answer : ! $answer, $o->position),
            $this->options,
        );

        $this->applyEdit($statement, $explanation, $options);
    }

    /** Refuses an edit made from a copy older than what is stored. */
    public function assertVersion(int $expected): void
    {
        if ($expected !== $this->version) {
            throw new QuestionEditedElsewhereException;
        }
    }

    /** A resent create: same topic, type, texts and answer, whatever the option ids. */
    public function hasSameContentAs(self $other): bool
    {
        return $this->topicId->equals($other->topicId)
            && $this->type === $other->type
            && $this->statement->value() === $other->statement->value()
            && $this->explanation === $other->explanation
            && $this->optionSignature() === $other->optionSignature();
    }

    /** Idempotent: deactivating an already-inactive question keeps the first timestamp. */
    public function deactivate(DateTimeImmutable $at): void
    {
        if ($this->deactivatedAt === null) {
            $this->deactivatedAt = $at;
            $this->activationChanged = true;
        }
    }

    public function reactivate(): void
    {
        if ($this->deactivatedAt !== null) {
            $this->deactivatedAt = null;
            $this->activationChanged = true;
        }
    }

    public function id(): QuestionId
    {
        return $this->id;
    }

    public function topicId(): TopicId
    {
        return $this->topicId;
    }

    public function type(): QuestionType
    {
        return $this->type;
    }

    public function statement(): QuestionStatement
    {
        return $this->statement;
    }

    public function explanation(): ?string
    {
        return $this->explanation;
    }

    /** @return list<QuestionOption> */
    public function options(): array
    {
        return $this->options;
    }

    public function version(): int
    {
        return $this->version;
    }

    /** The version this instance was restored at: what persistence compares against. */
    public function loadedVersion(): int
    {
        return $this->loadedVersion;
    }

    public function deactivatedAt(): ?DateTimeImmutable
    {
        return $this->deactivatedAt;
    }

    public function isActive(): bool
    {
        return $this->deactivatedAt === null;
    }

    public function isNew(): bool
    {
        return $this->isNew;
    }

    public function contentChanged(): bool
    {
        return $this->contentChanged;
    }

    public function activationChanged(): bool
    {
        return $this->activationChanged;
    }

    /** @return list<QuestionOptionId> options an edit dropped, to delete */
    public function removedOptionIds(): array
    {
        return $this->removedOptionIds;
    }

    /** @param list<QuestionOption> $options */
    private function applyEdit(QuestionStatement $statement, ?string $explanation, array $options): void
    {
        $this->statement = $statement;
        $this->explanation = self::cleanExplanation($explanation);
        $this->options = $options;

        // One bump per loaded copy, however many edits this request applies.
        if (! $this->contentChanged) {
            $this->version++;
            $this->contentChanged = true;
        }
    }

    /**
     * @param  list<OptionDraft>  $drafts
     * @param  array<string, QuestionOption>  $current  keyed by id value
     * @return list<QuestionOption>
     */
    private static function multipleChoiceOptions(array $drafts, array $current): array
    {
        $drafts = array_values($drafts);

        if (count($drafts) < self::MIN_OPTIONS || count($drafts) > self::MAX_OPTIONS) {
            throw new InvalidQuestionOptionsException('count');
        }

        $options = [];
        $usedIds = [];
        $keys = [];
        $correct = 0;

        foreach ($drafts as $position => $draft) {
            if ($draft->id !== null) {
                if (! isset($current[$draft->id]) || isset($usedIds[$draft->id])) {
                    throw new InvalidQuestionOptionsException('unknown_option');
                }

                $usedIds[$draft->id] = true;
                $id = $current[$draft->id]->id;
            } else {
                $id = QuestionOptionId::random();
            }

            $text = new OptionText($draft->text);

            if (isset($keys[$text->comparisonKey()])) {
                throw new InvalidQuestionOptionsException('duplicate');
            }

            $keys[$text->comparisonKey()] = true;
            $correct += $draft->correct ? 1 : 0;
            $options[] = new QuestionOption($id, $text, $draft->correct, $position);
        }

        if ($correct !== 1) {
            throw new InvalidQuestionOptionsException('correct');
        }

        return $options;
    }

    private static function cleanExplanation(?string $explanation): ?string
    {
        $trimmed = trim($explanation ?? '');

        if ($trimmed === '') {
            return null;
        }

        if (mb_strlen($trimmed) > self::EXPLANATION_MAX_LENGTH) {
            throw new InvalidArgumentException(
                sprintf('Question explanation cannot exceed %d characters.', self::EXPLANATION_MAX_LENGTH),
            );
        }

        return $trimmed;
    }

    /** @return list<array{string, bool}> */
    private function optionSignature(): array
    {
        return array_map(static fn (QuestionOption $o): array => [$o->text->value(), $o->correct], $this->options);
    }
}
