<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Domain\Service;

use App\Modules\Quiz\Domain\ValueObject\OptionCount;
use App\Modules\Quiz\Domain\ValueObject\QuestionStats;
use App\Modules\Quiz\Domain\ValueObject\SnapshotOption;
use App\Modules\Quiz\Domain\ValueObject\SummaryAnswer;

/**
 * Which questions a group of students gets wrong. Each student counts once per question,
 * by their latest answer (like the study list): a student who learned it stops counting.
 * Texts come from the question's most recent snapshot; a latest answer whose option is
 * not in it any more (removed by an edit) is counted apart, never dropped.
 */
final class QuestionSummary
{
    /**
     * @param  list<SummaryAnswer>  $answers  in any order
     * @return list<QuestionStats> most wrong first, then most answered, then by statement
     */
    public static function fromAnswers(array $answers): array
    {
        usort($answers, static fn (SummaryAnswer $a, SummaryAnswer $b): int => [$a->answeredAt, $a->attemptStartedAt, $a->attemptQuestionId] <=> [$b->answeredAt, $b->attemptStartedAt, $b->attemptQuestionId]);

        /** @var array<string, array<string, SummaryAnswer>> $latest question id → student id → answer */
        $latest = [];
        /** @var array<string, SummaryAnswer> $snapshot question id → most recent answer */
        $snapshot = [];

        foreach ($answers as $answer) {
            $latest[$answer->questionId][$answer->studentId] = $answer;
            $snapshot[$answer->questionId] = $answer;
        }

        $stats = [];

        foreach ($latest as $questionId => $byStudent) {
            $text = $snapshot[$questionId];
            $chosen = array_fill_keys(array_map(static fn (SnapshotOption $o): string => $o->id, $text->options), 0);
            $other = 0;
            $wrong = 0;

            foreach ($byStudent as $answer) {
                $wrong += $answer->correct ? 0 : 1;

                if (array_key_exists($answer->chosenOptionId, $chosen)) {
                    $chosen[$answer->chosenOptionId]++;
                } else {
                    $other++;
                }
            }

            $stats[] = new QuestionStats(
                (string) $questionId, $text->type, $text->statement, $text->correctOptionId, count($byStudent), $wrong,
                array_map(static fn (SnapshotOption $o): OptionCount => new OptionCount($o->id, $o->text, $chosen[$o->id]), $text->options),
                $other,
            );
        }

        usort($stats, static fn (QuestionStats $a, QuestionStats $b): int => [$b->wrongPercent(), $b->answered, $a->statement] <=> [$a->wrongPercent(), $a->answered, $b->statement]);

        return $stats;
    }
}
