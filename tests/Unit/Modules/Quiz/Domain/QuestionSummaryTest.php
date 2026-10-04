<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Quiz\Domain;

use App\Modules\Quiz\Domain\Service\QuestionSummary;
use App\Modules\Quiz\Domain\ValueObject\OptionCount;
use App\Modules\Quiz\Domain\ValueObject\QuestionStats;
use App\Modules\Quiz\Domain\ValueObject\SnapshotOption;
use App\Modules\Quiz\Domain\ValueObject\SummaryAnswer;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class QuestionSummaryTest extends TestCase
{
    private const TWO_OPTIONS = [['o-1', 'Certa'], ['o-2', 'Errada']];

    /** @param list<array{string, string}> $options id and text; o-1 is always the right one */
    private static function answer(
        string $student,
        string $question,
        string $chosen,
        string $answeredAt,
        string $startedAt = '2026-10-01 09:00',
        string $row = 'aq-1',
        array $options = self::TWO_OPTIONS,
        ?string $statement = null,
    ): SummaryAnswer {
        return new SummaryAnswer(
            $student, $question, $row, 'multiple_choice', $statement ?? "Pergunta $question",
            array_map(static fn (array $o): SnapshotOption => new SnapshotOption($o[0], $o[1]), $options),
            'o-1', $chosen, $chosen === 'o-1',
            new DateTimeImmutable($answeredAt), new DateTimeImmutable($startedAt),
        );
    }

    /** @return array<string, int> option id → chosen */
    private static function chosen(QuestionStats $stats): array
    {
        return array_column(array_map(static fn (OptionCount $o): array => [$o->id, $o->chosen], $stats->options), 1, 0);
    }

    public function test_no_answers_is_an_empty_list(): void
    {
        self::assertSame([], QuestionSummary::fromAnswers([]));
    }

    public function test_it_counts_the_latest_answer_of_each_student(): void
    {
        $stats = QuestionSummary::fromAnswers([
            self::answer('ana', 'q-1', 'o-1', '2026-10-01 11:00'),
            self::answer('ana', 'q-1', 'o-2', '2026-10-01 10:00'),
            self::answer('bia', 'q-1', 'o-2', '2026-10-01 10:00'),
        ]);

        self::assertCount(1, $stats);
        self::assertSame('q-1', $stats[0]->questionId);
        self::assertSame(2, $stats[0]->answered);
        self::assertSame(1, $stats[0]->wrong);
        self::assertSame(50, $stats[0]->wrongPercent());
        self::assertSame('o-1', $stats[0]->correctOptionId);
        self::assertSame(['o-1' => 1, 'o-2' => 1], self::chosen($stats[0]));
        self::assertSame(0, $stats[0]->otherChosen);
    }

    public function test_equal_times_fall_back_to_the_newer_attempt_then_the_row_id(): void
    {
        $byAttempt = QuestionSummary::fromAnswers([
            self::answer('ana', 'q-1', 'o-1', '2026-10-01 10:00', startedAt: '2026-10-01 09:30'),
            self::answer('ana', 'q-1', 'o-2', '2026-10-01 10:00', startedAt: '2026-10-01 09:00'),
        ]);
        $byRow = QuestionSummary::fromAnswers([
            self::answer('ana', 'q-1', 'o-2', '2026-10-01 10:00', row: 'aq-2'),
            self::answer('ana', 'q-1', 'o-1', '2026-10-01 10:00', row: 'aq-1'),
        ]);

        self::assertSame(0, $byAttempt[0]->wrong);
        self::assertSame(1, $byRow[0]->wrong);
    }

    public function test_texts_come_from_the_most_recent_snapshot_and_a_removed_option_goes_to_other_chosen(): void
    {
        $stats = QuestionSummary::fromAnswers([
            self::answer('ana', 'q-1', 'o-3', '2026-10-01 10:00', options: [['o-1', 'Certa'], ['o-2', 'Errada'], ['o-3', 'Antiga']], statement: 'Velha'),
            self::answer('bia', 'q-1', 'o-2', '2026-10-02 10:00', statement: 'Nova'),
        ]);

        self::assertSame('Nova', $stats[0]->statement);
        self::assertSame(['o-1' => 0, 'o-2' => 1], self::chosen($stats[0]));
        self::assertSame(1, $stats[0]->otherChosen);
        self::assertSame(2, $stats[0]->wrong);
    }

    public function test_the_percent_rounds_half_up(): void
    {
        $percent = static function (int $wrong, int $answered): int {
            $answers = [];

            for ($i = 0; $i < $answered; $i++) {
                $answers[] = self::answer("s-$i", 'q-1', $i < $wrong ? 'o-2' : 'o-1', '2026-10-01 10:00');
            }

            return QuestionSummary::fromAnswers($answers)[0]->wrongPercent();
        };

        self::assertSame(13, $percent(1, 8));
        self::assertSame(67, $percent(2, 3));
        self::assertSame(33, $percent(1, 3));
        self::assertSame(100, $percent(1, 1));
        self::assertSame(0, $percent(0, 4));
    }

    public function test_questions_are_ordered_by_wrong_percent_then_answered_then_statement(): void
    {
        $stats = QuestionSummary::fromAnswers([
            // q-low: 0% of 1
            self::answer('ana', 'q-low', 'o-1', '2026-10-01 10:00'),
            // q-half-two: 50% of 2
            self::answer('ana', 'q-half-two', 'o-2', '2026-10-01 10:00', statement: 'B'),
            self::answer('bia', 'q-half-two', 'o-1', '2026-10-01 10:00', statement: 'B'),
            // q-half-four: 50% of 4
            self::answer('ana', 'q-half-four', 'o-2', '2026-10-01 10:00'),
            self::answer('bia', 'q-half-four', 'o-2', '2026-10-01 10:00'),
            self::answer('cai', 'q-half-four', 'o-1', '2026-10-01 10:00'),
            self::answer('duda', 'q-half-four', 'o-1', '2026-10-01 10:00'),
            // q-half-two-a: 50% of 2, statement before "B"
            self::answer('ana', 'q-half-two-a', 'o-2', '2026-10-01 10:00', statement: 'A'),
            self::answer('bia', 'q-half-two-a', 'o-1', '2026-10-01 10:00', statement: 'A'),
            // q-all: 100% of 1
            self::answer('ana', 'q-all', 'o-2', '2026-10-01 10:00'),
        ]);

        self::assertSame(
            ['q-all', 'q-half-four', 'q-half-two-a', 'q-half-two', 'q-low'],
            array_map(static fn (QuestionStats $s): string => $s->questionId, $stats),
        );
    }
}
