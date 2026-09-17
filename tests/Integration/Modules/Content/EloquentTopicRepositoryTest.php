<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Content;

use App\Modules\Content\Domain\Entity\Topic;
use App\Modules\Content\Domain\Repository\TopicRepository;
use App\Modules\Content\Domain\ValueObject\TopicId;
use App\Modules\Content\Domain\ValueObject\TopicName;
use App\Modules\Content\Infrastructure\Persistence\TopicModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EloquentTopicRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_a_topic(): void
    {
        $id = TopicId::random();

        $this->repository()->save(new Topic($id, new TopicName('Tabela Periódica'), 'Grupos e períodos.', 3));

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
        $repository = $this->repository();

        $repository->save(new Topic($id, new TopicName('Átomos'), 'Primeira versão.', 1));
        $repository->save(new Topic($id, new TopicName('Átomos e Elementos'), 'Segunda versão.', 2));

        self::assertSame(1, TopicModel::query()->count());

        $row = TopicModel::query()->find($id->value());

        self::assertNotNull($row);
        self::assertSame('Átomos e Elementos', $row->getAttribute('name'));
        self::assertSame('Segunda versão.', $row->getAttribute('description'));
        self::assertSame(2, $row->getAttribute('position'));
    }

    private function repository(): TopicRepository
    {
        return $this->app->make(TopicRepository::class);
    }
}
