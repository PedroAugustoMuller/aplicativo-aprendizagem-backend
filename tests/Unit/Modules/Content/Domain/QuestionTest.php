<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Content\Domain;

use App\Modules\Content\Domain\Entity\Question;
use App\Modules\Content\Domain\Exception\InvalidQuestionOptionsException;
use App\Modules\Content\Domain\Exception\QuestionEditedElsewhereException;
use App\Modules\Content\Domain\ValueObject\OptionDraft;
use App\Modules\Content\Domain\ValueObject\QuestionId;
use App\Modules\Content\Domain\ValueObject\QuestionStatement;
use App\Modules\Content\Domain\ValueObject\QuestionType;
use App\Modules\Content\Domain\ValueObject\TopicId;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class QuestionTest extends TestCase
{
    public function test_a_multiple_choice_question_starts_at_version_1_with_ordered_options(): void
    {
        $question = $this->sodium();

        self::assertSame(QuestionType::MultipleChoice, $question->type());
        self::assertSame(1, $question->version());
        self::assertTrue($question->isNew());
        self::assertTrue($question->isActive());
        self::assertSame(['Na', 'S', 'So'], $this->texts($question));
        self::assertSame([0, 1, 2], array_map(fn ($o): int => $o->position, $question->options()));
        self::assertSame([true, false, false], array_map(fn ($o): bool => $o->correct, $question->options()));
    }

    public function test_fewer_than_two_or_more_than_five_options_are_rejected(): void
    {
        $this->assertRejected('count', fn () => $this->mc([['Na', true]]));
        $this->assertRejected('count', fn () => $this->mc([['A', true], ['B', false], ['C', false], ['D', false], ['E', false], ['F', false]]));
    }

    public function test_exactly_one_option_must_be_correct(): void
    {
        $this->assertRejected('correct', fn () => $this->mc([['A', false], ['B', false]]));
        $this->assertRejected('correct', fn () => $this->mc([['A', true], ['B', true]]));
    }

    public function test_options_that_differ_only_in_case_or_spacing_are_duplicates(): void
    {
        $this->assertRejected('duplicate', fn () => $this->mc([['Na', true], [' na ', false]]));
        $this->assertRejected('duplicate', fn () => $this->mc([['Cloreto de sódio', true], ['cloreto   de sódio', false]]));
    }

    public function test_different_characters_are_not_duplicates(): void
    {
        $question = $this->mc([['H₂O', true], ['H2O', false]]);

        self::assertSame(['H₂O', 'H2O'], $this->texts($question));
    }

    public function test_a_blank_option_is_rejected(): void
    {
        $this->assertRejected('empty', fn () => $this->mc([['Na', true], ['   ', false]]));
    }

    public function test_a_true_false_question_gets_the_two_fixed_options(): void
    {
        $question = Question::trueFalse(QuestionId::random(), TopicId::random(), new QuestionStatement('Elétrons ficam no núcleo.'), null, false);

        self::assertSame(QuestionType::TrueFalse, $question->type());
        self::assertSame(['Verdadeiro', 'Falso'], $this->texts($question));
        self::assertSame([false, true], array_map(fn ($o): bool => $o->correct, $question->options()));
    }

    public function test_an_edit_keeps_the_ids_sent_creates_new_ones_and_records_the_dropped(): void
    {
        $question = $this->restored($this->sodium());
        [$na, $s, $so] = $question->options();

        $question->editMultipleChoice(new QuestionStatement('Símbolo do sódio?'), 'Natrium.', [
            new OptionDraft($so->id->value(), 'So', false),
            new OptionDraft($na->id->value(), 'Na', true),
            new OptionDraft(null, 'Sd', false),
        ]);

        $options = $question->options();
        self::assertSame(['So', 'Na', 'Sd'], $this->texts($question));
        self::assertTrue($options[0]->id->equals($so->id));
        self::assertTrue($options[1]->id->equals($na->id));
        self::assertFalse($options[2]->id->equals($s->id));
        self::assertCount(1, $question->removedOptionIds());
        self::assertTrue($question->removedOptionIds()[0]->equals($s->id));
        self::assertSame(2, $question->version());
        self::assertSame(1, $question->loadedVersion());
        self::assertTrue($question->contentChanged());
        self::assertSame('Símbolo do sódio?', $question->statement()->value());
        self::assertSame('Natrium.', $question->explanation());
    }

    public function test_two_edits_in_one_request_bump_the_version_once(): void
    {
        $question = $this->restored($this->sodium());
        $drafts = array_map(fn ($o): OptionDraft => new OptionDraft($o->id->value(), $o->text->value(), $o->correct), $question->options());

        $question->editMultipleChoice(new QuestionStatement('Um'), null, $drafts);
        $question->editMultipleChoice(new QuestionStatement('Dois'), null, $drafts);

        self::assertSame(2, $question->version());
    }

    public function test_an_option_id_that_is_not_the_questions_or_is_repeated_is_rejected(): void
    {
        $question = $this->restored($this->sodium());
        $na = $question->options()[0];

        $this->assertRejected('unknown_option', fn () => $question->editMultipleChoice(new QuestionStatement('x'), null, [
            new OptionDraft('0199c0de-1a2b-7c3d-8e4f-5a6b7c8d9e0f', 'Na', true),
            new OptionDraft(null, 'S', false),
        ]));
        $this->assertRejected('unknown_option', fn () => $question->editMultipleChoice(new QuestionStatement('x'), null, [
            new OptionDraft($na->id->value(), 'Na', true),
            new OptionDraft($na->id->value(), 'S', false),
        ]));
    }

    public function test_editing_a_true_false_question_keeps_both_ids_and_flips_the_answer(): void
    {
        $question = $this->restored(Question::trueFalse(QuestionId::random(), TopicId::random(), new QuestionStatement('x'), null, true));
        [$true, $false] = $question->options();

        $question->editTrueFalse(new QuestionStatement('y'), null, false);

        self::assertTrue($question->options()[0]->id->equals($true->id));
        self::assertTrue($question->options()[1]->id->equals($false->id));
        self::assertSame([false, true], array_map(fn ($o): bool => $o->correct, $question->options()));
        self::assertSame([], $question->removedOptionIds());
    }

    public function test_the_type_cannot_change(): void
    {
        $tf = Question::trueFalse(QuestionId::random(), TopicId::random(), new QuestionStatement('x'), null, true);

        $this->assertRejected('type', fn () => $tf->editMultipleChoice(new QuestionStatement('x'), null, [new OptionDraft(null, 'A', true), new OptionDraft(null, 'B', false)]));
        $this->assertRejected('type', fn () => $this->sodium()->editTrueFalse(new QuestionStatement('x'), null, true));
    }

    public function test_a_stale_version_is_edited_elsewhere(): void
    {
        $question = $this->restored($this->sodium(), version: 3);

        $question->assertVersion(3);
        $this->expectException(QuestionEditedElsewhereException::class);
        $question->assertVersion(2);
    }

    public function test_a_blank_explanation_is_none_and_an_overlong_one_is_rejected(): void
    {
        self::assertNull($this->sodium('   ')->explanation());

        $this->expectException(InvalidArgumentException::class);
        $this->sodium(str_repeat('a', 1001));
    }

    public function test_deactivation_is_idempotent_and_does_not_bump_the_version(): void
    {
        $question = $this->restored($this->sodium());
        $first = new DateTimeImmutable('2026-10-02 10:00:00');

        $question->deactivate($first);
        $question->deactivate(new DateTimeImmutable('2026-10-02 11:00:00'));

        self::assertFalse($question->isActive());
        self::assertSame($first, $question->deactivatedAt());
        self::assertSame(1, $question->version());
        self::assertTrue($question->activationChanged());
        self::assertFalse($question->contentChanged());

        $question->reactivate();
        self::assertTrue($question->isActive());
    }

    public function test_same_content_ignores_option_ids(): void
    {
        $id = QuestionId::random();
        $topic = TopicId::random();
        $drafts = [new OptionDraft(null, 'Na', true), new OptionDraft(null, 'S', false)];
        $a = Question::multipleChoice($id, $topic, new QuestionStatement('Sódio?'), null, $drafts);
        $b = Question::multipleChoice($id, $topic, new QuestionStatement('Sódio?'), null, $drafts);
        $c = Question::multipleChoice($id, $topic, new QuestionStatement('Sódio?'), 'Outra.', $drafts);
        $d = Question::multipleChoice($id, TopicId::random(), new QuestionStatement('Sódio?'), null, $drafts);

        self::assertTrue($a->hasSameContentAs($b));
        self::assertFalse($a->hasSameContentAs($c));
        self::assertFalse($a->hasSameContentAs($d));
    }

    private function sodium(?string $explanation = null): Question
    {
        return Question::multipleChoice(QuestionId::random(), TopicId::random(), new QuestionStatement('Qual é o símbolo do sódio?'), $explanation, [
            new OptionDraft(null, 'Na', true),
            new OptionDraft(null, 'S', false),
            new OptionDraft(null, 'So', false),
        ]);
    }

    /** @param list<array{string, bool}> $options */
    private function mc(array $options): Question
    {
        return Question::multipleChoice(
            QuestionId::random(),
            TopicId::random(),
            new QuestionStatement('Pergunta?'),
            null,
            array_map(fn (array $o): OptionDraft => new OptionDraft(null, $o[0], $o[1]), $options),
        );
    }

    private function restored(Question $question, int $version = 1): Question
    {
        return Question::restore(
            $question->id(),
            $question->topicId(),
            $question->type(),
            $question->statement(),
            $question->explanation(),
            $question->options(),
            $version,
            null,
        );
    }

    /** @return list<string> */
    private function texts(Question $question): array
    {
        return array_map(fn ($o): string => $o->text->value(), $question->options());
    }

    private function assertRejected(string $reason, callable $build): void
    {
        try {
            $build();
            self::fail("Expected the options to be rejected for '$reason'.");
        } catch (InvalidQuestionOptionsException $e) {
            self::assertSame($reason, $e->reason());
        }
    }
}
