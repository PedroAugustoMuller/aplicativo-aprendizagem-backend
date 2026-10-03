<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Command\ReorderTopics;

use App\Modules\Content\Application\Service\AuthoringGate;
use App\Modules\Content\Domain\Exception\TopicOrderStaleException;
use App\Modules\Content\Domain\Repository\TopicRepository;
use App\Modules\Content\Domain\ValueObject\SubjectId;
use App\Modules\Content\Domain\ValueObject\TopicId;

/**
 * The client sends the whole order, so two people moving topics at once can
 * never interleave into an order nobody chose: the second sees order_stale.
 */
final readonly class ReorderTopicsHandler
{
    public function __construct(private TopicRepository $topics, private AuthoringGate $gate) {}

    public function handle(ReorderTopicsCommand $command): void
    {
        $subjectId = new SubjectId($command->subjectId);
        $this->gate->assertCanWrite($command->actor, $subjectId);

        $order = array_map(static fn (string $id): TopicId => new TopicId($id), $command->ids);

        if (! $this->topics->reorder($subjectId, $order)) {
            throw new TopicOrderStaleException;
        }
    }
}
