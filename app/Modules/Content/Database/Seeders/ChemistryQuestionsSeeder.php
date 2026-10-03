<?php

declare(strict_types=1);

namespace App\Modules\Content\Database\Seeders;

use App\Modules\Content\Domain\Entity\Question;
use App\Modules\Content\Domain\Repository\QuestionRepository;
use App\Modules\Content\Domain\ValueObject\OptionDraft;
use App\Modules\Content\Domain\ValueObject\QuestionId;
use App\Modules\Content\Domain\ValueObject\QuestionStatement;
use App\Modules\Content\Domain\ValueObject\TopicId;
use App\Modules\Content\Infrastructure\Persistence\TopicModel;
use App\Shared\Infrastructure\Persistence\EloquentAttribute;
use Illuminate\Database\Seeder;

/**
 * Example questions so local development and the quiz sub-project start with
 * data. Text is Portuguese because it is content. Fixed ids: reseeding skips
 * what exists. Requires ChemistryTopicsSeeder (topics are found by name).
 */
final class ChemistryQuestionsSeeder extends Seeder
{
    /** @var list<array{string, string, string, string, list<array{string, bool}>}> */
    private const MULTIPLE_CHOICE = [
        ['0192f0a0-0000-7000-8000-000000000101', 'Tabela Periódica', 'Qual é o símbolo químico do sódio?',
            'O símbolo Na vem do nome em latim, natrium.', [['Na', true], ['S', false], ['So', false], ['Sd', false]]],
        ['0192f0a0-0000-7000-8000-000000000103', 'Matéria e suas Transformações', 'Como se chama a passagem da água do estado líquido para o gasoso?',
            'Vaporização é a passagem do líquido para o gasoso.', [['Vaporização', true], ['Fusão', false], ['Solidificação', false], ['Condensação', false]]],
        ['0192f0a0-0000-7000-8000-000000000105', 'Ligações Químicas', 'Que tipo de ligação une os átomos na molécula de H₂O?',
            'Hidrogênio e oxigênio compartilham elétrons: ligação covalente.', [['Covalente', true], ['Iônica', false], ['Metálica', false]]],
    ];

    /** @var list<array{string, string, string, string, bool}> */
    private const TRUE_FALSE = [
        ['0192f0a0-0000-7000-8000-000000000102', 'Tabela Periódica', 'Elementos de um mesmo grupo da tabela periódica têm propriedades químicas parecidas.',
            'Eles têm o mesmo número de elétrons na camada de valência.', true],
        ['0192f0a0-0000-7000-8000-000000000104', 'Átomos e Elementos Químicos', 'Os elétrons ficam no núcleo do átomo.',
            'No núcleo ficam prótons e nêutrons; os elétrons ficam na eletrosfera.', false],
    ];

    public function run(QuestionRepository $questions): void
    {
        foreach (self::MULTIPLE_CHOICE as [$id, $topic, $statement, $explanation, $options]) {
            $topicId = $this->topicId($topic);

            if ($topicId === null || $questions->findById(new QuestionId($id)) !== null) {
                continue;
            }

            $questions->save(Question::multipleChoice(
                new QuestionId($id), $topicId, new QuestionStatement($statement), $explanation,
                array_map(static fn (array $o): OptionDraft => new OptionDraft(null, $o[0], $o[1]), $options),
            ));
        }

        foreach (self::TRUE_FALSE as [$id, $topic, $statement, $explanation, $answer]) {
            $topicId = $this->topicId($topic);

            if ($topicId === null || $questions->findById(new QuestionId($id)) !== null) {
                continue;
            }

            $questions->save(Question::trueFalse(new QuestionId($id), $topicId, new QuestionStatement($statement), $explanation, $answer));
        }
    }

    private function topicId(string $name): ?TopicId
    {
        $topic = TopicModel::query()
            ->where('subject_id', SubjectsSeeder::CHEMISTRY_ID)
            ->where('name', $name)
            ->first();

        return $topic instanceof TopicModel ? new TopicId(EloquentAttribute::string($topic->getKey(), 'topics.id')) : null;
    }
}
