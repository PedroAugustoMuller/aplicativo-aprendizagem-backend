<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Quiz\Domain;

use App\Modules\Quiz\Domain\Entity\Attempt;
use App\Modules\Quiz\Domain\Entity\AttemptQuestion;
use App\Modules\Quiz\Domain\Exception\InvalidAnswerOptionException;
use App\Modules\Quiz\Domain\Exception\QuestionAlreadyAnsweredException;
use App\Modules\Quiz\Domain\Exception\TopicHasNoQuestionsException;
use App\Modules\Quiz\Domain\ValueObject\AnswerId;
use App\Modules\Quiz\Domain\ValueObject\AttemptId;
use App\Modules\Quiz\Domain\ValueObject\AttemptQuestionId;
use App\Modules\Quiz\Domain\ValueObject\SnapshotOption;
use App\Shared\Domain\Contract\BankQuestion;
use App\Shared\Domain\Exception\IdempotencyConflictException;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Modules\Quiz\Support\BankFixtures;
use Tests\Unit\Modules\Quiz\Support\ReversingShuffler;

final class AttemptTest extends TestCase
{
    private const STUDENT = '0192f0a0-0000-7000-8000-00000000b001';

    /** @param list<BankQuestion> $bank */
    private function start(array $bank, string $at = '2026-10-03 10:00:00'): Attempt
    {
        return Attempt::start(AttemptId::random(), self::STUDENT, BankFixtures::topic(), $bank, new ReversingShuffler, new DateTimeImmutable($at));
    }

    private function first(Attempt $attempt): AttemptQuestion
    {
        return $attempt->questions()[0];
    }

    /** Answers $question with option $part of bank question $n, both clocks at $at. */
    private function answer(Attempt $attempt, AttemptQuestion $question, string $optionId, ?AnswerId $id = null, string $at = '2026-10-03 10:01:00'): mixed
    {
        return $attempt->answer($question->id(), $optionId, $id ?? AnswerId::random(), new DateTimeImmutable($at), new DateTimeImmutable($at));
    }

    public function test_it_draws_at_most_ten_questions_in_shuffled_order(): void
    {
        $attempt = $this->start(BankFixtures::choices(12));

        self::assertCount(10, $attempt->questions());
        self::assertSame(range(0, 9), array_map(static fn (AttemptQuestion $q): int => $q->position(), $attempt->questions()));
        // Reversed: the newest bank question comes first.
        self::assertSame(BankFixtures::id(12, 0), $this->first($attempt)->questionId());
        self::assertSame(['total' => 10, 'answered' => 0, 'correct' => 0], (array) $attempt->score());
        self::assertFalse($attempt->isCompleted());
    }

    public function test_a_topic_with_fewer_questions_uses_them_all(): void
    {
        self::assertCount(3, $this->start(BankFixtures::choices(3))->questions());
    }

    public function test_an_empty_bank_is_refused(): void
    {
        $this->expectException(TopicHasNoQuestionsException::class);

        $this->start([]);
    }

    public function test_multiple_choice_options_are_shuffled_but_true_false_keeps_its_order(): void
    {
        $attempt = $this->start([BankFixtures::choice(1), BankFixtures::trueFalse(2)]);
        [$trueFalse, $choice] = $attempt->questions();

        self::assertSame(['1-c', '1-b', '1-a'], array_map(static fn (SnapshotOption $o): string => $o->text, $choice->options()));
        self::assertSame(['Verdadeiro', 'Falso'], array_map(static fn (SnapshotOption $o): string => $o->text, $trueFalse->options()));
        self::assertSame(BankFixtures::id(1, 1), $choice->correctOptionId());
        self::assertSame(BankFixtures::id(2, 2), $trueFalse->correctOptionId());
    }

    public function test_a_correct_answer_is_graded_against_the_snapshot(): void
    {
        $attempt = $this->start([BankFixtures::choice(1)]);
        $question = $this->first($attempt);

        $result = $this->answer($attempt, $question, BankFixtures::id(1, 1));

        self::assertSame([
            'questionId' => $question->id()->value(),
            'optionId' => BankFixtures::id(1, 1),
            'correct' => true,
            'correctOptionId' => BankFixtures::id(1, 1),
            'explanation' => 'Porque sim.',
        ], (array) $result);
        self::assertSame($question, $attempt->lastAnswered());
    }

    public function test_a_wrong_answer_reveals_the_correct_option(): void
    {
        $attempt = $this->start([BankFixtures::choice(1), BankFixtures::choice(2)]);

        $result = $attempt->answer($this->first($attempt)->id(), BankFixtures::id(2, 3), AnswerId::random(), new DateTimeImmutable('2026-10-03 10:01'), new DateTimeImmutable('2026-10-03 10:01'));

        self::assertFalse($result->correct);
        self::assertSame(BankFixtures::id(2, 1), $result->correctOptionId);
        self::assertSame(['total' => 2, 'answered' => 1, 'correct' => 0], (array) $attempt->score());
    }

    public function test_resending_the_same_answer_returns_the_stored_result_and_changes_nothing(): void
    {
        $attempt = $this->start([BankFixtures::choice(1), BankFixtures::choice(2)]);
        $question = $this->first($attempt);
        $id = AnswerId::random();
        $first = $this->answer($attempt, $question, BankFixtures::id(2, 1), $id);

        $again = $this->answer($attempt, $question, BankFixtures::id(2, 1), $id, '2026-10-03 10:05:00');

        self::assertEquals($first, $again);
        self::assertNull($attempt->lastAnswered());
        self::assertEquals(new DateTimeImmutable('2026-10-03 10:01:00'), $question->answeredAt());
    }

    public function test_a_second_answer_with_another_id_is_refused(): void
    {
        $attempt = $this->start([BankFixtures::choice(1), BankFixtures::choice(2)]);
        $question = $this->first($attempt);
        $this->answer($attempt, $question, BankFixtures::id(2, 1));

        $this->expectException(QuestionAlreadyAnsweredException::class);

        $this->answer($attempt, $question, BankFixtures::id(2, 2));
    }

    public function test_an_option_of_another_question_is_refused(): void
    {
        $attempt = $this->start([BankFixtures::choice(1), BankFixtures::choice(2)]);

        $this->expectException(InvalidAnswerOptionException::class);

        $this->answer($attempt, $this->first($attempt), BankFixtures::id(1, 1));
    }

    public function test_a_question_outside_the_attempt_is_refused(): void
    {
        $attempt = $this->start([BankFixtures::choice(1)]);

        $this->expectException(InvalidAnswerOptionException::class);

        $attempt->answer(AttemptQuestionId::random(), BankFixtures::id(1, 1), AnswerId::random(), new DateTimeImmutable, new DateTimeImmutable);
    }

    public function test_the_same_answer_id_on_another_question_is_an_idempotency_conflict(): void
    {
        $attempt = $this->start([BankFixtures::choice(1), BankFixtures::choice(2)]);
        [$second, $first] = $attempt->questions();
        $id = AnswerId::random();
        $this->answer($attempt, $second, BankFixtures::id(2, 1), $id);

        $this->expectException(IdempotencyConflictException::class);

        $this->answer($attempt, $first, BankFixtures::id(1, 1), $id);
    }

    public function test_answered_at_is_kept_between_the_start_and_the_arrival(): void
    {
        $attempt = $this->start([BankFixtures::choice(1), BankFixtures::choice(2)], '2026-10-03 10:00:00');
        [$a, $b] = $attempt->questions();
        $received = new DateTimeImmutable('2026-10-03 10:30:00');

        $attempt->answer($a->id(), BankFixtures::id(2, 1), AnswerId::random(), new DateTimeImmutable('2000-01-01 00:00:00'), $received);
        $attempt->answer($b->id(), BankFixtures::id(1, 1), AnswerId::random(), new DateTimeImmutable('2099-01-01 00:00:00'), $received);

        self::assertEquals(new DateTimeImmutable('2026-10-03 10:00:00'), $a->answeredAt());
        self::assertEquals($received, $b->answeredAt());
    }

    public function test_the_last_answer_completes_the_attempt(): void
    {
        $attempt = $this->start([BankFixtures::choice(1), BankFixtures::choice(2)]);
        [$a, $b] = $attempt->questions();
        $this->answer($attempt, $a, BankFixtures::id(2, 1));
        self::assertNull($attempt->completedAt());

        $this->answer($attempt, $b, BankFixtures::id(1, 2), null, '2026-10-03 10:09:00');

        self::assertTrue($attempt->isCompleted());
        self::assertEquals(new DateTimeImmutable('2026-10-03 10:09:00'), $attempt->completedAt());
        self::assertSame(['total' => 2, 'answered' => 2, 'correct' => 1], (array) $attempt->score());
    }

    public function test_it_belongs_to_the_student_who_started_it(): void
    {
        $attempt = $this->start([BankFixtures::choice(1)]);

        self::assertTrue($attempt->isOwnedBy(self::STUDENT));
        self::assertFalse($attempt->isOwnedBy('0192f0a0-0000-7000-8000-00000000b002'));
    }
}
