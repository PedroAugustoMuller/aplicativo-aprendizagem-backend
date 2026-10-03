<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Command\UpdateQuestion;

use App\Modules\Content\Application\DTO\QuestionView;
use App\Modules\Content\Application\Service\AuthoringGate;
use App\Modules\Content\Domain\Exception\InvalidQuestionOptionsException;
use App\Modules\Content\Domain\Exception\QuestionNotFoundException;
use App\Modules\Content\Domain\Exception\TopicNotFoundException;
use App\Modules\Content\Domain\Repository\QuestionRepository;
use App\Modules\Content\Domain\Repository\TopicRepository;
use App\Modules\Content\Domain\ValueObject\QuestionId;
use App\Modules\Content\Domain\ValueObject\QuestionStatement;
use App\Modules\Content\Domain\ValueObject\QuestionType;

final readonly class UpdateQuestionHandler
{
    public function __construct(
        private TopicRepository $topics,
        private QuestionRepository $questions,
        private AuthoringGate $gate,
    ) {}

    public function handle(UpdateQuestionCommand $command): QuestionView
    {
        $question = $this->questions->findById(new QuestionId($command->id)) ?? throw new QuestionNotFoundException;
        $topic = $this->topics->findById($question->topicId()) ?? throw new TopicNotFoundException;
        $this->gate->assertCanWrite($command->actor, $topic->subjectId());

        // Fails fast on an old copy; the repository re-checks atomically on write.
        $question->assertVersion($command->version);
        $statement = new QuestionStatement($command->statement);

        if ($question->type() === QuestionType::MultipleChoice) {
            if ($command->options === null || $command->answer !== null) {
                throw new InvalidQuestionOptionsException('type');
            }

            $question->editMultipleChoice($statement, $command->explanation, $command->options);
        } else {
            if ($command->answer === null || $command->options !== null) {
                throw new InvalidQuestionOptionsException('type');
            }

            $question->editTrueFalse($statement, $command->explanation, $command->answer);
        }

        $this->questions->save($question);

        return QuestionView::of($question);
    }
}
