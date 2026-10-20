<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Command\SuggestQuestions;

use App\Modules\Content\Application\Service\AuthoringGate;
use App\Modules\Content\Domain\Exception\NoValidSuggestionsException;
use App\Modules\Content\Domain\Exception\SubjectNotFoundException;
use App\Modules\Content\Domain\Exception\TopicNotFoundException;
use App\Modules\Content\Domain\Repository\SubjectRepository;
use App\Modules\Content\Domain\Repository\TopicRepository;
use App\Modules\Content\Domain\ValueObject\QuestionType;
use App\Modules\Content\Domain\ValueObject\TopicId;

/** Asks the generator for a topic's questions and keeps the valid ones. Writes nothing. */
final readonly class SuggestQuestionsHandler
{
    public function __construct(
        private TopicRepository $topics,
        private SubjectRepository $subjects,
        private AuthoringGate $gate,
        private QuestionGenerator $generator,
        private SuggestionFilter $filter,
    ) {}

    public function handle(SuggestQuestionsCommand $command): SuggestionResult
    {
        $topic = $this->topics->findById(new TopicId($command->topicId)) ?? throw new TopicNotFoundException;
        $this->gate->assertCanWrite($command->actor, $topic->subjectId());
        $subject = $this->subjects->findById($topic->subjectId()) ?? throw new SubjectNotFoundException;

        $types = array_values(array_map(QuestionType::from(...), $command->types));

        $raw = $this->generator->generate(new SuggestionRequest(
            $subject->name()->value(),
            $topic->name()->value(),
            $topic->description(),
            $command->count,
            $types,
            SuggestionDifficulty::from($command->difficulty),
            self::blankToNull($command->notes),
            self::blankToNull($command->sourceText),
        ));

        $kept = $this->filter->keep($raw, $topic->id(), $types, $command->count);

        if ($kept === []) {
            throw new NoValidSuggestionsException;
        }

        return new SuggestionResult($kept, count($raw));
    }

    private static function blankToNull(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : $value;
    }
}
