<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Persistence;

use App\Modules\Content\Domain\Entity\Question;
use App\Modules\Content\Domain\Exception\QuestionEditedElsewhereException;
use App\Modules\Content\Domain\Repository\QuestionRepository;
use App\Modules\Content\Domain\ValueObject\QuestionId;
use App\Modules\Content\Domain\ValueObject\QuestionOptionId;
use Illuminate\Support\Facades\DB;

final class EloquentQuestionRepository implements QuestionRepository
{
    /** Kept options are parked this far up before taking their final positions. */
    private const POSITION_PARKING_OFFSET = 1000;

    public function __construct(private readonly QuestionMapper $mapper) {}

    public function findById(QuestionId $id): ?Question
    {
        $model = QuestionModel::query()->find($id->value());

        if (! $model instanceof QuestionModel) {
            return null;
        }

        $options = QuestionOptionModel::query()->where('question_id', $id->value())->orderBy('position')->get();

        return $this->mapper->toDomain($model, array_values($options->all()));
    }

    public function save(Question $question): void
    {
        DB::transaction(function () use ($question): void {
            $id = $question->id()->value();

            if ($question->isNew()) {
                QuestionModel::query()->create(['id' => $id] + $this->mapper->toAttributes($question));
                $this->writeOptions($question);

                return;
            }

            if ($question->contentChanged()) {
                // The version check and the write are one statement: no window
                // in which a second editor could slip between them.
                $updated = QuestionModel::query()
                    ->whereKey($id)
                    ->where('version', $question->loadedVersion())
                    ->update([
                        'statement' => $question->statement()->value(),
                        'explanation' => $question->explanation(),
                        'version' => $question->version(),
                    ]);

                if ($updated === 0) {
                    throw new QuestionEditedElsewhereException;
                }

                $removed = array_map(static fn (QuestionOptionId $o): string => $o->value(), $question->removedOptionIds());

                if ($removed !== []) {
                    QuestionOptionModel::query()->where('question_id', $id)->whereIn('id', $removed)->delete();
                }

                // Rewriting positions in place would trip the (question_id, position)
                // unique index halfway through a swap: park them out of the way first.
                QuestionOptionModel::query()->where('question_id', $id)->increment('position', self::POSITION_PARKING_OFFSET);
                $this->writeOptions($question);
            }

            if ($question->activationChanged()) {
                QuestionModel::query()->whereKey($id)->update(['deactivated_at' => $question->deactivatedAt()]);
            }
        });
    }

    private function writeOptions(Question $question): void
    {
        foreach ($question->options() as $option) {
            QuestionOptionModel::query()->updateOrCreate(
                ['id' => $option->id->value()],
                [
                    'question_id' => $question->id()->value(),
                    'text' => $option->text->value(),
                    'is_correct' => $option->correct,
                    'position' => $option->position,
                ],
            );
        }
    }
}
