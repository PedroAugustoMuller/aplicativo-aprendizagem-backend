<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Content;

use App\Modules\Content\Domain\Entity\Topic;
use App\Modules\Content\Domain\Exception\TopicNameAlreadyTakenException;
use App\Modules\Content\Domain\Repository\TopicRepository;
use App\Modules\Content\Domain\ValueObject\SubjectId;
use App\Modules\Content\Domain\ValueObject\TopicId;
use App\Modules\Content\Domain\ValueObject\TopicName;
use App\Modules\Content\Infrastructure\Persistence\SubjectModel;
use App\Modules\Content\Infrastructure\Persistence\TopicModel;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EloquentTopicRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_a_topic(): void
    {
        $id = TopicId::random();
        $subjectId = $this->createSubject();

        $this->repository()->save(new Topic($id, $subjectId, new TopicName('Tabela Periódica'), 'Grupos e períodos.', 3));

        // Asserted against the stored row rather than read back through the
        // repository: there is no read-by-id path, and adding one purely so this
        // test could use it would be the speculative surface this plan removed.
        $row = TopicModel::query()->find($id->value());

        self::assertNotNull($row);
        self::assertSame('Tabela Periódica', $row->getAttribute('name'));
        self::assertSame('Grupos e períodos.', $row->getAttribute('description'));
        self::assertSame(3, $row->getAttribute('position'));
    }

    public function test_saving_twice_updates_instead_of_duplicating(): void
    {
        $id = TopicId::random();
        $subjectId = $this->createSubject();
        $repository = $this->repository();

        $repository->save(new Topic($id, $subjectId, new TopicName('Átomos'), 'Primeira versão.', 1));
        $repository->save(new Topic($id, $subjectId, new TopicName('Átomos e Elementos'), 'Segunda versão.', 2));

        self::assertSame(1, TopicModel::query()->count());

        $row = TopicModel::query()->find($id->value());

        self::assertNotNull($row);
        self::assertSame('Átomos e Elementos', $row->getAttribute('name'));
        self::assertSame('Segunda versão.', $row->getAttribute('description'));
        self::assertSame(2, $row->getAttribute('position'));
    }

    public function test_find_by_id_restores_the_stored_topic(): void
    {
        $subjectId = $this->createSubject();
        $id = TopicId::random();
        $this->repository()->save(new Topic($id, $subjectId, new TopicName('Átomos'), 'Prótons.', 2));

        $topic = $this->repository()->findById($id);

        self::assertNotNull($topic);
        self::assertFalse($topic->isNew());
        self::assertSame('Átomos', $topic->name()->value());
        self::assertSame(2, $topic->position());
        self::assertTrue($topic->isActive());
        self::assertNull($this->repository()->findById(TopicId::random()));
    }

    public function test_a_rename_and_a_concurrent_deactivation_both_survive(): void
    {
        $subjectId = $this->createSubject();
        $id = TopicId::random();
        $this->repository()->save(new Topic($id, $subjectId, new TopicName('Átomos'), '', 0));

        // Two requests loaded the same topic before either saved.
        $renaming = $this->repository()->findById($id);
        $deactivating = $this->repository()->findById($id);
        self::assertNotNull($renaming);
        self::assertNotNull($deactivating);

        $renaming->rename(new TopicName('Átomos e Íons'));
        $deactivating->deactivate(new DateTimeImmutable);
        $this->repository()->save($renaming);
        $this->repository()->save($deactivating);

        $stored = $this->repository()->findById($id);
        self::assertNotNull($stored);
        self::assertSame('Átomos e Íons', $stored->name()->value());
        self::assertFalse($stored->isActive());
    }

    public function test_a_rename_and_a_concurrent_description_change_both_survive(): void
    {
        $subjectId = $this->createSubject();
        $id = TopicId::random();
        $this->repository()->save(new Topic($id, $subjectId, new TopicName('Átomos'), 'Velha.', 0));

        $renaming = $this->repository()->findById($id);
        $describing = $this->repository()->findById($id);
        self::assertNotNull($renaming);
        self::assertNotNull($describing);

        $renaming->rename(new TopicName('Átomos e Íons'));
        $describing->changeDescription('Nova.');
        $this->repository()->save($renaming);
        $this->repository()->save($describing);

        $stored = $this->repository()->findById($id);
        self::assertNotNull($stored);
        self::assertSame('Átomos e Íons', $stored->name()->value());
        self::assertSame('Nova.', $stored->description());
    }

    public function test_append_with_a_name_already_stored_reports_the_clash(): void
    {
        $subjectId = $this->createSubject();
        $this->repository()->append(TopicId::random(), $subjectId, new TopicName('Átomos'), '');

        $this->expectException(TopicNameAlreadyTakenException::class);

        $this->repository()->append(TopicId::random(), $subjectId, new TopicName('Átomos'), '');
    }

    public function test_saving_a_new_topic_with_a_name_already_stored_reports_the_clash(): void
    {
        $subjectId = $this->createSubject();
        $this->repository()->append(TopicId::random(), $subjectId, new TopicName('Átomos'), '');

        $this->expectException(TopicNameAlreadyTakenException::class);

        $this->repository()->save(new Topic(TopicId::random(), $subjectId, new TopicName('Átomos'), '', 5));
    }

    public function test_renaming_to_a_name_already_stored_reports_the_clash(): void
    {
        $subjectId = $this->createSubject();
        $this->repository()->append(TopicId::random(), $subjectId, new TopicName('Átomos'), '');
        $other = $this->repository()->append(TopicId::random(), $subjectId, new TopicName('Íons'), '');

        $topic = $this->repository()->findById($other->id());
        self::assertNotNull($topic);
        $topic->rename(new TopicName('Átomos'));

        $this->expectException(TopicNameAlreadyTakenException::class);

        $this->repository()->save($topic);
    }

    public function test_append_places_a_topic_after_the_last_one(): void
    {
        $subjectId = $this->createSubject();
        $repository = $this->repository();

        $first = $repository->append(TopicId::random(), $subjectId, new TopicName('Primeiro'), '');
        $repository->save(new Topic(TopicId::random(), $subjectId, new TopicName('Sétimo'), '', 7));
        $next = $repository->append(TopicId::random(), $subjectId, new TopicName('Oitavo'), 'Depois.');

        self::assertSame(0, $first->position());
        self::assertSame(8, $next->position());
        self::assertSame(3, TopicModel::query()->where('subject_id', $subjectId->value())->count());
    }

    public function test_a_name_is_taken_case_insensitively_within_the_same_subject_only(): void
    {
        $chemistry = $this->createSubject();
        $biology = $this->createSubject('Biologia');
        $id = TopicId::random();
        $this->repository()->save(new Topic($id, $chemistry, new TopicName('Átomos'), '', 0));

        self::assertTrue($this->repository()->nameTakenByAnother($chemistry, new TopicName('átomos'), TopicId::random()));
        self::assertFalse($this->repository()->nameTakenByAnother($chemistry, new TopicName('Átomos'), $id));
        self::assertFalse($this->repository()->nameTakenByAnother($biology, new TopicName('Átomos'), TopicId::random()));
    }

    public function test_reorder_rewrites_positions_only_for_the_exact_set_of_topics(): void
    {
        $subjectId = $this->createSubject();
        $a = TopicId::random();
        $b = TopicId::random();
        $this->repository()->save(new Topic($a, $subjectId, new TopicName('A'), '', 1));
        $this->repository()->save(new Topic($b, $subjectId, new TopicName('B'), '', 2));

        self::assertFalse($this->repository()->reorder($subjectId, [$b]));
        self::assertFalse($this->repository()->reorder($subjectId, [$b, $a, TopicId::random()]));
        self::assertFalse($this->repository()->reorder($subjectId, [$b, $b]));
        self::assertSame(1, $this->repository()->findById($a)?->position());

        self::assertTrue($this->repository()->reorder($subjectId, [$b, $a]));
        self::assertSame(0, $this->repository()->findById($b)?->position());
        self::assertSame(1, $this->repository()->findById($a)?->position());
    }

    private function repository(): TopicRepository
    {
        return $this->app->make(TopicRepository::class);
    }

    private function createSubject(string $name = 'Química'): SubjectId
    {
        $id = SubjectId::random();

        SubjectModel::query()->create(['id' => $id->value(), 'name' => $name]);

        return $id;
    }
}
