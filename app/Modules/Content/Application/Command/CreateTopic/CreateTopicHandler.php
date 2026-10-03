<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Command\CreateTopic;

use App\Modules\Content\Application\DTO\TopicView;
use App\Modules\Content\Application\Service\AuthoringGate;
use App\Modules\Content\Domain\Exception\TopicNameAlreadyTakenException;
use App\Modules\Content\Domain\Repository\TopicRepository;
use App\Modules\Content\Domain\ValueObject\SubjectId;
use App\Modules\Content\Domain\ValueObject\TopicId;
use App\Modules\Content\Domain\ValueObject\TopicName;
use App\Shared\Domain\Exception\IdempotencyConflictException;

final readonly class CreateTopicHandler
{
    public function __construct(private TopicRepository $topics, private AuthoringGate $gate) {}

    public function handle(CreateTopicCommand $command): TopicView
    {
        $subjectId = new SubjectId($command->subjectId);
        $this->gate->assertCanWrite($command->actor, $subjectId);

        $id = new TopicId($command->id);
        $name = new TopicName($command->name);
        $description = trim($command->description);
        $existing = $this->topics->findById($id);

        if ($existing !== null) {
            $same = $existing->subjectId()->equals($subjectId)
                && $existing->name()->value() === $name->value()
                && $existing->description() === $description;

            if (! $same) {
                throw new IdempotencyConflictException;
            }

            return TopicView::of($existing);
        }

        if ($this->topics->nameTakenByAnother($subjectId, $name, $id)) {
            throw new TopicNameAlreadyTakenException($name->value());
        }

        return TopicView::of($this->topics->append($id, $subjectId, $name, $description));
    }
}
