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
        ['0192f0a0-0000-7000-8000-000000000106', 'Tabela Periódica', 'O símbolo químico do ouro é Au.',
            'Au vem do nome em latim, aurum.', true],
        ['0192f0a0-0000-7000-8000-000000000107', 'Tabela Periódica', 'A tabela periódica organiza os elementos em ordem crescente de número atômico.',
            'O número atômico é a quantidade de prótons no núcleo.', true],
        ['0192f0a0-0000-7000-8000-000000000108', 'Tabela Periódica', 'Os gases nobres ficam na primeira coluna da tabela periódica.',
            'Os gases nobres ficam no grupo 18, a última coluna.', false],
        ['0192f0a0-0000-7000-8000-000000000109', 'Tabela Periódica', 'O hidrogênio é o elemento de número atômico 1.',
            'O hidrogênio tem um único próton no núcleo.', true],
        ['0192f0a0-0000-7000-8000-000000000110', 'Tabela Periódica', 'As linhas horizontais da tabela periódica são chamadas de grupos.',
            'As linhas são os períodos; as colunas são os grupos, ou famílias.', false],
        ['0192f0a0-0000-7000-8000-000000000111', 'Tabela Periódica', 'O símbolo químico do potássio é P.',
            'O símbolo do potássio é K, do latim kalium; P é o fósforo.', false],
        ['0192f0a0-0000-7000-8000-000000000112', 'Tabela Periódica', 'A maioria dos elementos da tabela periódica são metais.',
            'Os metais ocupam a maior parte da tabela, à esquerda e no centro.', true],
        ['0192f0a0-0000-7000-8000-000000000113', 'Tabela Periódica', 'O oxigênio é um gás nobre.',
            'O oxigênio fica no grupo 16; os gases nobres ficam no grupo 18.', false],
        ['0192f0a0-0000-7000-8000-000000000114', 'Tabela Periódica', 'O símbolo químico do ferro é Fe.',
            'Fe vem do nome em latim, ferrum.', true],
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
