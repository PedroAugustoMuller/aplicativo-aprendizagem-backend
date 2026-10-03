<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Quiz;

use App\Modules\Identity\Infrastructure\Persistence\UserModel;
use App\Modules\Quiz\Domain\Entity\Attempt;
use App\Modules\Quiz\Domain\Exception\QuestionAlreadyAnsweredException;
use App\Modules\Quiz\Domain\Repository\AttemptRepository;
use App\Modules\Quiz\Domain\Service\Shuffler;
use App\Modules\Quiz\Domain\ValueObject\AnswerId;
use App\Modules\Quiz\Domain\ValueObject\AttemptId;
use App\Shared\Domain\Contract\QuestionBank;
use App\Shared\Infrastructure\Persistence\EloquentAttribute;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Support\ActsAsUsers;
use Tests\TestCase;
use Tests\Unit\Modules\Quiz\Support\ReversingShuffler;

final class EloquentAttemptRepositoryTest extends TestCase
{
    use ActsAsUsers;
    use RefreshDatabase;

    private string $topicId;

    private string $studentId;

    /** @var array{id: string, options: list<string>} */
    private array $sodium;

    protected function setUp(): void
    {
        parent::setUp();
        $this->topicId = $this->makeTopic($this->makeSubject());
        $this->studentId = EloquentAttribute::string($this->makeUser('student')->getKey(), 'users.id');
        $this->sodium = $this->makeQuestionWithOptions($this->topicId, [['Na', true], ['S', false], ['So', false]]);
    }

    private function repository(): AttemptRepository
    {
        return $this->app->make(AttemptRepository::class);
    }

    private function started(?string $studentId = null): Attempt
    {
        $bank = $this->app->make(QuestionBank::class);
        $topic = $bank->topic($this->topicId) ?? self::fail('topic missing');
        $attempt = Attempt::start(AttemptId::random(), $studentId ?? $this->studentId, $topic, $bank->activeQuestions($this->topicId), new ReversingShuffler, new DateTimeImmutable('2026-10-03 10:00:00'));
        $this->repository()->add($attempt);

        return $attempt;
    }

    /** A fresh load each call: two loads model two requests racing. */
    private function load(Attempt $attempt): Attempt
    {
        $loaded = $this->repository()->findById($attempt->id());
        self::assertNotNull($loaded);

        return $loaded;
    }

    private function answerFirst(Attempt $attempt, string $optionId, ?AnswerId $id = null): void
    {
        $attempt->answer($attempt->questions()[0]->id(), $optionId, $id ?? AnswerId::random(), new DateTimeImmutable('2026-10-03 10:01:00'), new DateTimeImmutable('2026-10-03 10:02:00'));
        $this->repository()->saveAnswer($attempt);
    }

    public function test_an_attempt_round_trips_with_its_snapshot(): void
    {
        $attempt = $this->started();

        $loaded = $this->repository()->findById($attempt->id()) ?? self::fail('not stored');

        self::assertSame($this->studentId, $loaded->studentId());
        self::assertSame($this->topicId, $loaded->topicId());
        self::assertEquals(new DateTimeImmutable('2026-10-03 10:00:00'), $loaded->startedAt());
        $question = $loaded->questions()[0];
        self::assertSame($this->sodium['id'], $question->questionId());
        self::assertSame(['So', 'S', 'Na'], array_map(static fn ($o): string => $o->text, $question->options()));
        self::assertSame($this->sodium['options'][0], $question->correctOptionId());
        self::assertSame('Explicação.', $question->explanation());
        self::assertNull($question->result());
    }

    public function test_an_answer_is_stored_and_the_last_one_completes_the_attempt(): void
    {
        $attempt = $this->started();

        $this->answerFirst($attempt, $this->sodium['options'][1]);

        $row = DB::table('quiz_attempt_questions')->where('attempt_id', $attempt->id()->value())->first();
        self::assertNotNull($row);
        self::assertSame($this->sodium['options'][1], $row->chosen_option_id);
        self::assertSame($this->sodium['options'][1], $row->option_id);
        self::assertFalse((bool) $row->is_correct);
        self::assertNotNull(DB::table('quiz_attempts')->where('id', $attempt->id()->value())->value('completed_at'));
        self::assertNull($this->repository()->findOpen($this->studentId, $this->topicId));
    }

    public function test_find_open_returns_only_an_unfinished_attempt_of_that_student_and_topic(): void
    {
        $attempt = $this->started();
        $other = EloquentAttribute::string($this->makeUser('student')->getKey(), 'users.id');

        self::assertTrue($this->repository()->findOpen($this->studentId, $this->topicId)?->id()->equals($attempt->id()));
        self::assertNull($this->repository()->findOpen($other, $this->topicId));
    }

    public function test_the_snapshot_survives_the_bank_option_being_removed(): void
    {
        $attempt = $this->started();
        $this->answerFirst($attempt, $this->sodium['options'][0]);

        // An edit that drops an option deletes its row (Content never deletes questions).
        DB::table('question_options')->where('id', $this->sodium['options'][0])->delete();

        $row = DB::table('quiz_attempt_questions')->where('attempt_id', $attempt->id()->value())->first();
        self::assertNotNull($row);
        self::assertNull($row->option_id);
        self::assertSame($this->sodium['options'][0], $row->chosen_option_id);
        $loaded = $this->repository()->findById($attempt->id()) ?? self::fail('not stored');
        self::assertTrue($loaded->questions()[0]->result()?->correct);
        self::assertContains('Na', array_map(static fn ($o): string => $o->text, $loaded->questions()[0]->options()));
    }

    public function test_an_answer_to_an_option_removed_before_answering_is_graded_and_left_unlinked(): void
    {
        $attempt = $this->started();
        DB::table('question_options')->where('id', $this->sodium['options'][0])->delete();

        $this->answerFirst($attempt, $this->sodium['options'][0]);

        $row = DB::table('quiz_attempt_questions')->where('attempt_id', $attempt->id()->value())->first();
        self::assertNotNull($row);
        self::assertNull($row->option_id);
        self::assertTrue((bool) $row->is_correct);
    }

    public function test_two_loads_answering_the_same_question_let_only_the_first_land(): void
    {
        $attempt = $this->started();
        $first = $this->load($attempt);
        $second = $this->load($attempt);
        $this->answerFirst($first, $this->sodium['options'][0]);

        $this->expectException(QuestionAlreadyAnsweredException::class);

        $this->answerFirst($second, $this->sodium['options'][1]);
    }

    public function test_the_database_refuses_a_second_open_attempt_for_the_same_student_and_topic(): void
    {
        $this->started();

        $this->expectException(QueryException::class);

        $this->started();
    }

    public function test_the_student_lock_is_taken_inside_a_transaction(): void
    {
        DB::transaction(fn () => $this->repository()->lockStudent($this->studentId));

        // Reaching here is the assertion: Postgres rejects the call outside a valid statement.
        $this->addToAssertionCount(1);
    }

    public function test_the_container_binds_a_real_shuffler(): void
    {
        $shuffler = $this->app->make(Shuffler::class);

        self::assertEqualsCanonicalizing([1, 2, 3], $shuffler->shuffle([1, 2, 3]));
        self::assertNotInstanceOf(ReversingShuffler::class, $shuffler);
    }

    public function test_deleting_a_student_is_blocked_while_they_have_attempts(): void
    {
        $this->started();

        $this->expectException(QueryException::class);

        UserModel::query()->whereKey($this->studentId)->delete();
    }
}
