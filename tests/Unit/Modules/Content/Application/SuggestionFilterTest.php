<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Content\Application;

use App\Modules\Content\Application\Command\SuggestQuestions\QuestionSuggestion;
use App\Modules\Content\Application\Command\SuggestQuestions\SuggestionFilter;
use App\Modules\Content\Domain\ValueObject\OptionDraft;
use App\Modules\Content\Domain\ValueObject\QuestionType;
use App\Modules\Content\Domain\ValueObject\TopicId;
use PHPUnit\Framework\TestCase;

final class SuggestionFilterTest extends TestCase
{
    private const TOPIC = '0190a2b4-0000-7000-8000-0000000070c1';

    private const BOTH = [QuestionType::MultipleChoice, QuestionType::TrueFalse];

    public function test_valid_suggestions_are_kept_and_trimmed(): void
    {
        $kept = $this->keep([
            self::mc('  Símbolo do sódio?  ', [['Na', true], [' S ', false]], ' Natrium. '),
            self::tf('O sódio é um metal.', true),
        ]);

        self::assertCount(2, $kept);
        self::assertSame('Símbolo do sódio?', $kept[0]->statement);
        self::assertSame('Natrium.', $kept[0]->explanation);
        self::assertSame('S', $kept[0]->options[1]->text);
        self::assertNull($kept[0]->answer);
        self::assertSame([], $kept[1]->options);
        self::assertTrue($kept[1]->answer);
    }

    public function test_a_multiple_choice_without_exactly_one_correct_option_is_dropped(): void
    {
        self::assertSame([], $this->keep([
            self::mc('Nenhuma certa?', [['A', false], ['B', false]]),
            self::mc('Duas certas?', [['A', true], ['B', true]]),
        ]));
    }

    public function test_option_count_duplicates_and_empty_texts_are_dropped(): void
    {
        self::assertSame([], $this->keep([
            self::mc('Uma só?', [['A', true]]),
            self::mc('Seis?', [['A', true], ['B', false], ['C', false], ['D', false], ['E', false], ['F', false]]),
            self::mc('Repetidas?', [['Na', true], ['na ', false]]),
            self::mc('Vazia?', [['Na', true], ['  ', false]]),
            self::mc('   ', [['Na', true], ['S', false]]),
        ]));
    }

    public function test_over_length_texts_are_dropped(): void
    {
        self::assertSame([], $this->keep([
            self::mc(str_repeat('a', 1001), [['Na', true], ['S', false]]),
            self::mc('Explicação longa?', [['Na', true], ['S', false]], str_repeat('a', 1001)),
            self::mc('Alternativa longa?', [['Na', true], [str_repeat('a', 201), false]]),
        ]));
    }

    public function test_a_true_false_without_an_answer_is_dropped(): void
    {
        self::assertSame([], $this->keep([new QuestionSuggestion(QuestionType::TrueFalse, 'Sem resposta.', null, [], null)]));
    }

    public function test_a_type_that_was_not_requested_is_dropped(): void
    {
        $kept = (new SuggestionFilter)->keep(
            [self::tf('O sódio é um metal.', true), self::mc('Símbolo?', [['Na', true], ['S', false]])],
            new TopicId(self::TOPIC),
            [QuestionType::MultipleChoice],
            10,
        );

        self::assertCount(1, $kept);
        self::assertSame(QuestionType::MultipleChoice, $kept[0]->type);
    }

    public function test_suggestions_beyond_the_requested_count_are_dropped(): void
    {
        $kept = (new SuggestionFilter)->keep(
            [self::tf('Um.', true), self::tf('Dois.', false), self::tf('Três.', true)],
            new TopicId(self::TOPIC),
            self::BOTH,
            2,
        );

        self::assertSame(['Um.', 'Dois.'], array_map(static fn (QuestionSuggestion $s): string => $s->statement, $kept));
    }

    /**
     * @param  list<QuestionSuggestion>  $raw
     * @return list<QuestionSuggestion>
     */
    private function keep(array $raw): array
    {
        return (new SuggestionFilter)->keep($raw, new TopicId(self::TOPIC), self::BOTH, 10);
    }

    /** @param list<array{string, bool}> $options */
    private static function mc(string $statement, array $options, ?string $explanation = null): QuestionSuggestion
    {
        return new QuestionSuggestion(
            QuestionType::MultipleChoice,
            $statement,
            $explanation,
            array_map(static fn (array $o): OptionDraft => new OptionDraft(null, $o[0], $o[1]), $options),
            null,
        );
    }

    private static function tf(string $statement, bool $answer): QuestionSuggestion
    {
        return new QuestionSuggestion(QuestionType::TrueFalse, $statement, null, [], $answer);
    }
}
