<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Command\SetQuestionActivation;

use App\Modules\Content\Application\DTO\QuestionView;
use App\Modules\Content\Application\Service\AuthoringGate;
use App\Modules\Content\Domain\Exception\QuestionNotFoundException;
use App\Modules\Content\Domain\Exception\TopicNotFoundException;
use App\Modules\Content\Domain\Repository\QuestionRepository;
use App\Modules\Content\Domain\Repository\TopicRepository;
use App\Modules\Content\Domain\ValueObject\QuestionId;
use DateTimeImmutable;

/** Writes only `deactivated_at` and never bumps the version: an open edit form stays valid. */
final readonly class SetQuestionActivationHandler
{
    public function __construct(
        private TopicRepository $topics,
        private QuestionRepository $questions,
        private AuthoringGate $gate,
    ) {}

    public function handle(SetQuestionActivationCommand $command): QuestionView
    {
        $question = $this->questions->findById(new QuestionId($command->id)) ?? throw new QuestionNotFoundException;
        $topic = $this->topics->findById($question->topicId()) ?? throw new TopicNotFoundException;
        $this->gate->assertCanWrite($command->actor, $topic->subjectId());

        if ($command->active) {
            $question->reactivate();
        } else {
            $question->deactivate(new DateTimeImmutable);
        }

        $this->questions->save($question);

        return QuestionView::of($question);
    }
}
