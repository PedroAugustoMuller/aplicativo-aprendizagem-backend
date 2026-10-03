<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Command\UpdateTopic;

use App\Modules\Content\Application\DTO\TopicView;
use App\Modules\Content\Application\Service\AuthoringGate;
use App\Modules\Content\Domain\Exception\TopicNameAlreadyTakenException;
use App\Modules\Content\Domain\Exception\TopicNotFoundException;
use App\Modules\Content\Domain\Repository\TopicRepository;
use App\Modules\Content\Domain\ValueObject\TopicId;
use App\Modules\Content\Domain\ValueObject\TopicName;

final readonly class UpdateTopicHandler
{
    public function __construct(private TopicRepository $topics, private AuthoringGate $gate) {}

    public function handle(UpdateTopicCommand $command): TopicView
    {
        $topic = $this->topics->findById(new TopicId($command->id)) ?? throw new TopicNotFoundException;
        $this->gate->assertCanWrite($command->actor, $topic->subjectId());

        if ($command->name !== null) {
            $name = new TopicName($command->name);

            if ($this->topics->nameTakenByAnother($topic->subjectId(), $name, $topic->id())) {
                throw new TopicNameAlreadyTakenException($name->value());
            }

            $topic->rename($name);
        }

        if ($command->description !== null) {
            $topic->changeDescription($command->description);
        }

        $this->topics->save($topic);

        return TopicView::of($topic);
    }
}
