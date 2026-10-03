<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Content;

use App\Shared\Domain\Contract\BankOption;
use App\Shared\Domain\Contract\QuestionBank;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Support\ActsAsUsers;
use Tests\TestCase;

final class EloquentQuestionBankTest extends TestCase
{
    use ActsAsUsers;
    use RefreshDatabase;

    private function bank(): QuestionBank
    {
        return $this->app->make(QuestionBank::class);
    }

    public function test_a_topic_reports_its_subject_and_is_available_while_both_are_active(): void
    {
        $subjectId = $this->makeSubject();
        $topicId = $this->makeTopic($subjectId);

        $topic = $this->bank()->topic($topicId);

        self::assertNotNull($topic);
        self::assertSame($topicId, $topic->id);
        self::assertSame($subjectId, $topic->subjectId);
        self::assertTrue($topic->available);
    }

    public function test_a_deactivated_topic_or_subject_is_unavailable(): void
    {
        $subjectId = $this->makeSubject();
        $topicId = $this->makeTopic($subjectId);

        DB::table('topics')->where('id', $topicId)->update(['deactivated_at' => now()]);
        self::assertFalse($this->availability($topicId));

        DB::table('topics')->where('id', $topicId)->update(['deactivated_at' => null]);
        DB::table('subjects')->where('id', $subjectId)->update(['deactivated_at' => now()]);
        self::assertFalse($this->availability($topicId));
    }

    private function availability(string $topicId): bool
    {
        $topic = $this->bank()->topic($topicId);
        self::assertNotNull($topic);

        return $topic->available;
    }

    public function test_an_unknown_or_malformed_topic_is_null(): void
    {
        self::assertNull($this->bank()->topic('0192f0a0-0000-7000-8000-00000000dead'));
        self::assertNull($this->bank()->topic('not-a-uuid'));
        self::assertSame([], $this->bank()->activeQuestions('not-a-uuid'));
    }

    public function test_only_active_questions_come_with_their_options_in_position_order(): void
    {
        $topicId = $this->makeTopic($this->makeSubject());
        $kept = $this->makeQuestion($topicId);
        $hidden = $this->makeQuestion($topicId);
        DB::table('questions')->where('id', $hidden)->update(['deactivated_at' => now()]);

        $questions = $this->bank()->activeQuestions($topicId);

        self::assertCount(1, $questions);
        self::assertSame($kept, $questions[0]->id);
        self::assertSame('true_false', $questions[0]->type);
        self::assertSame('O sódio é um metal.', $questions[0]->statement);
        self::assertNull($questions[0]->explanation);
        self::assertSame(['Verdadeiro', 'Falso'], array_map(static fn (BankOption $o): string => $o->text, $questions[0]->options));
        self::assertSame([true, false], array_map(static fn (BankOption $o): bool => $o->correct, $questions[0]->options));
    }
}
