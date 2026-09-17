<?php

declare(strict_types=1);

namespace App\Modules\Content\Database\Seeders;

use App\Modules\Content\Domain\Entity\Topic;
use App\Modules\Content\Domain\Repository\TopicRepository;
use App\Modules\Content\Domain\ValueObject\TopicId;
use App\Modules\Content\Domain\ValueObject\TopicName;
use App\Modules\Content\Infrastructure\Persistence\TopicModel;
use App\Shared\Infrastructure\Persistence\EloquentAttribute;
use Illuminate\Database\Seeder;

/**
 * The chemistry syllabus taught to 9th grade at E.M.E.F. Dom Pedro II.
 * Topic text is Portuguese because it is content, not code.
 */
final class ChemistryTopicsSeeder extends Seeder
{
    private const TOPICS = [
        ['Matéria e suas Transformações', 'Estados físicos, mudanças de estado e propriedades da matéria.', 1],
        ['Átomos e Elementos Químicos', 'Estrutura atômica, prótons, nêutrons e elétrons.', 2],
        ['Tabela Periódica', 'Organização dos elementos em grupos e períodos.', 3],
        ['Ligações Químicas', 'Ligações iônicas, covalentes e metálicas.', 4],
        ['Substâncias e Misturas', 'Substâncias puras, misturas homogêneas e heterogêneas, métodos de separação.', 5],
        ['Reações Químicas', 'Equações químicas, balanceamento e tipos de reação.', 6],
    ];

    public function run(TopicRepository $topics): void
    {
        foreach (self::TOPICS as [$name, $description, $position]) {
            $existing = TopicModel::query()->where('name', $name)->first();

            $topics->save(new Topic(
                $existing instanceof TopicModel
                    ? new TopicId(EloquentAttribute::string($existing->getKey(), 'topics.id'))
                    : TopicId::random(),
                new TopicName($name),
                $description,
                $position,
            ));
        }
    }
}
