<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\Repository;

use App\Modules\Content\Domain\Entity\Topic;
use App\Modules\Content\Domain\ValueObject\SubjectId;
use App\Modules\Content\Domain\ValueObject\TopicId;
use App\Modules\Content\Domain\ValueObject\TopicName;

interface TopicRepository
{
    public function findById(TopicId $id): ?Topic;

    /** Case-insensitive, within one subject, deactivated topics included. */
    public function nameTakenByAnother(SubjectId $subjectId, TopicName $name, TopicId $except): bool;

    /** Inserts a new topic after the subject's last one; simultaneous appends get distinct positions. */
    public function append(TopicId $id, SubjectId $subjectId, TopicName $name, string $description): Topic;

    /** A new topic is written whole; a restored one only in the columns that changed. */
    public function save(Topic $topic): void;

    /**
     * Rewrites positions 0..n-1 in this order. Returns false and writes nothing
     * unless $order holds exactly the subject's topics, each once.
     *
     * @param  list<TopicId>  $order
     */
    public function reorder(SubjectId $subjectId, array $order): bool;
}
