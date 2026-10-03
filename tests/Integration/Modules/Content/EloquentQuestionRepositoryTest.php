<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Content;

use App\Modules\Content\Domain\Entity\Question;
use App\Modules\Content\Domain\Exception\QuestionEditedElsewhereException;
use App\Modules\Content\Domain\Repository\QuestionRepository;
use App\Modules\Content\Domain\ValueObject\OptionDraft;
use App\Modules\Content\Domain\ValueObject\QuestionId;
use App\Modules\Content\Domain\ValueObject\QuestionStatement;
use App\Modules\Content\Domain\ValueObject\QuestionType;
use App\Modules\Content\Domain\ValueObject\TopicId;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Support\ActsAsUsers;
use Tests\TestCase;

final class EloquentQuestionRepositoryTest extends TestCase
{
    use ActsAsUsers;
    use RefreshDatabase;

    private TopicId $topicId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->topicId = new TopicId($this->makeTopic($this->makeSubject()));
    }

    public function test_a_new_question_round_trips_with_its_options_in_order(): void
    {
        $question = $this->sodium();
        $this->repository()->save($question);

        $stored = $this->repository()->findById($question->id());

        self::assertNotNull($stored);
        self::assertFalse($stored->isNew());
        self::assertSame(QuestionType::MultipleChoice, $stored->type());
        self::assertSame('Qual é o símbolo do sódio?', $stored->statement()->value());
        self::assertSame('Natrium.', $stored->explanation());
        self::assertSame(1, $stored->version());
        self::assertSame(['Na', 'S', 'So'], array_map(fn ($o): string => $o->text->value(), $stored->options()));
        self::assertTrue($stored->options()[0]->id->equals($question->options()[0]->id));
        self::assertTrue($stored->options()[0]->correct);
        self::assertNull($this->repository()->findById(QuestionId::random()));
    }

    public function test_a_true_false_question_round_trips(): void
    {
        $question = Question::trueFalse(QuestionId::random(), $this->topicId, new QuestionStatement('O sódio é um metal.'), null, true);
        $this->repository()->save($question);

        $stored = $this->repository()->findById($question->id());

        self::assertNotNull($stored);
        self::assertSame(['Verdadeiro', 'Falso'], array_map(fn ($o): string => $o->text->value(), $stored->options()));
        self::assertNull($stored->explanation());
    }

    public function test_an_edit_that_swaps_and_drops_options_keeps_ids(): void
    {
        $question = $this->sodium();
        $this->repository()->save($question);
        [$na, $s, $so] = $question->options();

        $loaded = $this->repository()->findById($question->id());
        self::assertNotNull($loaded);
        $loaded->editMultipleChoice(new QuestionStatement('Sódio?'), null, [
            new OptionDraft($s->id->value(), 'S', false),
            new OptionDraft($na->id->value(), 'Na', true),
        ]);
        $this->repository()->save($loaded);

        $stored = $this->repository()->findById($question->id());
        self::assertNotNull($stored);
        self::assertSame(2, $stored->version());
        self::assertSame(['S', 'Na'], array_map(fn ($o): string => $o->text->value(), $stored->options()));
        self::assertTrue($stored->options()[0]->id->equals($s->id));
        self::assertTrue($stored->options()[1]->id->equals($na->id));
        self::assertSame([0, 1], array_map(fn ($o): int => $o->position, $stored->options()));
        self::assertSame(0, DB::table('question_options')->where('id', $so->id->value())->count());
    }

    public function test_a_content_save_from_a_stale_copy_is_refused_and_writes_nothing(): void
    {
        $question = $this->sodium();
        $this->repository()->save($question);
        $first = $this->repository()->findById($question->id());
        $second = $this->repository()->findById($question->id());
        self::assertNotNull($first);
        self::assertNotNull($second);
        $drafts = array_map(fn ($o): OptionDraft => new OptionDraft($o->id->value(), $o->text->value(), $o->correct), $question->options());

        $first->editMultipleChoice(new QuestionStatement('Primeira edição'), null, $drafts);
        $this->repository()->save($first);
        $second->editMultipleChoice(new QuestionStatement('Segunda edição'), null, $drafts);

        try {
            $this->repository()->save($second);
            self::fail('Expected the stale save to be refused.');
        } catch (QuestionEditedElsewhereException) {
            // expected
        }

        $stored = $this->repository()->findById($question->id());
        self::assertNotNull($stored);
        self::assertSame('Primeira edição', $stored->statement()->value());
        self::assertSame(2, $stored->version());
    }

    public function test_a_deactivation_alongside_a_content_edit_keeps_both(): void
    {
        $question = $this->sodium();
        $this->repository()->save($question);
        $editing = $this->repository()->findById($question->id());
        $hiding = $this->repository()->findById($question->id());
        self::assertNotNull($editing);
        self::assertNotNull($hiding);

        $editing->editMultipleChoice(new QuestionStatement('Editada'), null, array_map(
            fn ($o): OptionDraft => new OptionDraft($o->id->value(), $o->text->value(), $o->correct),
            $question->options(),
        ));
        $hiding->deactivate(new DateTimeImmutable);
        $this->repository()->save($editing);
        $this->repository()->save($hiding);

        $stored = $this->repository()->findById($question->id());
        self::assertNotNull($stored);
        self::assertSame('Editada', $stored->statement()->value());
        self::assertFalse($stored->isActive());
    }

    private function sodium(): Question
    {
        return Question::multipleChoice(QuestionId::random(), $this->topicId, new QuestionStatement('Qual é o símbolo do sódio?'), 'Natrium.', [
            new OptionDraft(null, 'Na', true),
            new OptionDraft(null, 'S', false),
            new OptionDraft(null, 'So', false),
        ]);
    }

    private function repository(): QuestionRepository
    {
        return $this->app->make(QuestionRepository::class);
    }
}
