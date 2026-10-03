<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Persistence;

use App\Modules\Content\Domain\Entity\Topic;
use App\Modules\Content\Domain\Repository\TopicRepository;
use App\Modules\Content\Domain\ValueObject\SubjectId;
use App\Modules\Content\Domain\ValueObject\TopicId;
use App\Modules\Content\Domain\ValueObject\TopicName;
use App\Shared\Infrastructure\Persistence\EloquentAttribute;
use Illuminate\Support\Facades\DB;

final class EloquentTopicRepository implements TopicRepository
{
    public function __construct(private readonly TopicMapper $mapper) {}

    public function findById(TopicId $id): ?Topic
    {
        $model = TopicModel::query()->find($id->value());

        return $model instanceof TopicModel ? $this->mapper->toDomain($model) : null;
    }

    /**
     * Case-insensitive on purpose: the (subject_id, name) unique index stays
     * case-sensitive as a last line of defence, this check is the business rule.
     */
    public function nameTakenByAnother(SubjectId $subjectId, TopicName $name, TopicId $except): bool
    {
        return TopicModel::query()
            ->where('subject_id', $subjectId->value())
            ->whereRaw('lower(name) = ?', [mb_strtolower($name->value())])
            ->whereKeyNot($except->value())
            ->exists();
    }

    public function append(TopicId $id, SubjectId $subjectId, TopicName $name, string $description): Topic
    {
        return DB::transaction(function () use ($id, $subjectId, $name, $description): Topic {
            // max()+1 is only safe while no one else computes it for this subject:
            // the subject row lock serialises appends (and reorders) per subject.
            $this->lockSubject($subjectId);

            $max = TopicModel::query()->where('subject_id', $subjectId->value())->max('position');
            $topic = new Topic($id, $subjectId, $name, trim($description), is_numeric($max) ? (int) $max + 1 : 0);

            TopicModel::query()->create(['id' => $id->value()] + $this->mapper->toAttributes($topic));

            return $topic;
        });
    }

    public function save(Topic $topic): void
    {
        if ($topic->isNew()) {
            TopicModel::query()->updateOrCreate(['id' => $topic->id()->value()], $this->mapper->toAttributes($topic));

            return;
        }

        // Only what this request changed: writing the whole snapshot back would
        // undo a concurrent rename or (de)activation of the same topic.
        $changes = [];

        if ($topic->detailsChanged()) {
            $changes['name'] = $topic->name()->value();
            $changes['description'] = $topic->description();
        }

        if ($topic->activationChanged()) {
            $changes['deactivated_at'] = $topic->deactivatedAt();
        }

        if ($changes !== []) {
            TopicModel::query()->whereKey($topic->id()->value())->update($changes);
        }
    }

    public function reorder(SubjectId $subjectId, array $order): bool
    {
        return DB::transaction(function () use ($subjectId, $order): bool {
            $this->lockSubject($subjectId);

            $current = [];

            foreach (TopicModel::query()->where('subject_id', $subjectId->value())->get(['id']) as $model) {
                $current[] = EloquentAttribute::string($model->getKey(), 'topics.id');
            }

            $wanted = array_map(static fn (TopicId $id): string => $id->value(), $order);
            $sortedCurrent = $current;
            $sortedWanted = $wanted;
            sort($sortedCurrent);
            sort($sortedWanted);

            // Same multiset, so a missing, extra or repeated id all fail here.
            if ($sortedCurrent !== $sortedWanted) {
                return false;
            }

            foreach ($wanted as $position => $id) {
                TopicModel::query()->whereKey($id)->update(['position' => $position]);
            }

            return true;
        });
    }

    private function lockSubject(SubjectId $subjectId): void
    {
        SubjectModel::query()->whereKey($subjectId->value())->lockForUpdate()->first();
    }
}
