<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Command\CreateQuestion;

use App\Modules\Content\Application\DTO\QuestionView;
use App\Modules\Content\Application\Service\AuthoringGate;
use App\Modules\Content\Domain\Entity\Question;
use App\Modules\Content\Domain\Exception\InvalidQuestionOptionsException;
use App\Modules\Content\Domain\Exception\TopicNotFoundException;
use App\Modules\Content\Domain\Repository\QuestionRepository;
use App\Modules\Content\Domain\Repository\TopicRepository;
use App\Modules\Content\Domain\ValueObject\QuestionId;
use App\Modules\Content\Domain\ValueObject\QuestionStatement;
use App\Modules\Content\Domain\ValueObject\QuestionType;
use App\Modules\Content\Domain\ValueObject\TopicId;
use App\Shared\Domain\Exception\IdempotencyConflictException;

final readonly class CreateQuestionHandler
{
    public function __construct(
        private TopicRepository $topics,
        private QuestionRepository $questions,
        private AuthoringGate $gate,
    ) {}

    public function handle(CreateQuestionCommand $command): QuestionView
    {
        $topic = $this->topics->findById(new TopicId($command->topicId)) ?? throw new TopicNotFoundException;
        $this->gate->assertCanWrite($command->actor, $topic->subjectId());

        $candidate = $this->build($command, $topic->id());
        $existing = $this->questions->findById($candidate->id());

        if ($existing !== null) {
            if (! $existing->hasSameContentAs($candidate)) {
                throw new IdempotencyConflictException;
            }

            return QuestionView::of($existing);
        }

        $this->questions->save($candidate);

        return QuestionView::of($candidate);
    }

    private function build(CreateQuestionCommand $command, TopicId $topicId): Question
    {
        $id = new QuestionId($command->id);
        $statement = new QuestionStatement($command->statement);

        return match (QuestionType::from($command->type)) {
            QuestionType::MultipleChoice => Question::multipleChoice(
                $id, $topicId, $statement, $command->explanation,
                $command->options ?? throw new InvalidQuestionOptionsException('count'),
            ),
            QuestionType::TrueFalse => Question::trueFalse(
                $id, $topicId, $statement, $command->explanation,
                $command->answer ?? throw new InvalidQuestionOptionsException('type'),
            ),
        };
    }
}
