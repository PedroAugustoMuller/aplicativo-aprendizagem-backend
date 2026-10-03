<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Command\SetTopicActivation;

use App\Modules\Content\Application\DTO\TopicView;
use App\Modules\Content\Application\Service\AuthoringGate;
use App\Modules\Content\Domain\Exception\TopicNotFoundException;
use App\Modules\Content\Domain\Repository\TopicRepository;
use App\Modules\Content\Domain\ValueObject\TopicId;
use DateTimeImmutable;

/** Deactivate and reactivate: both idempotent, both write only `deactivated_at`. */
final readonly class SetTopicActivationHandler
{
    public function __construct(private TopicRepository $topics, private AuthoringGate $gate) {}

    public function handle(SetTopicActivationCommand $command): TopicView
    {
        $topic = $this->topics->findById(new TopicId($command->id)) ?? throw new TopicNotFoundException;
        $this->gate->assertCanWrite($command->actor, $topic->subjectId());

        if ($command->active) {
            $topic->reactivate();
        } else {
            $topic->deactivate(new DateTimeImmutable);
        }

        $this->topics->save($topic);

        return TopicView::of($topic);
    }
}
