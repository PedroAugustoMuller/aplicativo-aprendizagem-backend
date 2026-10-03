<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Infrastructure\Persistence;

use App\Modules\Quiz\Domain\Entity\Attempt;
use App\Modules\Quiz\Domain\Exception\QuestionAlreadyAnsweredException;
use App\Modules\Quiz\Domain\Repository\AttemptRepository;
use App\Modules\Quiz\Domain\ValueObject\AttemptId;
use Illuminate\Support\Facades\DB;

final class EloquentAttemptRepository implements AttemptRepository
{
    public function __construct(private readonly AttemptMapper $mapper) {}

    public function findById(AttemptId $id): ?Attempt
    {
        return $this->load(QuizAttemptModel::query()->find($id->value()));
    }

    public function findByIdForUpdate(AttemptId $id): ?Attempt
    {
        return $this->load(QuizAttemptModel::query()->lockForUpdate()->find($id->value()));
    }

    public function findOpen(string $studentId, string $topicId): ?Attempt
    {
        return $this->load(QuizAttemptModel::query()
            ->where('student_id', $studentId)
            ->where('topic_id', $topicId)
            ->whereNull('completed_at')
            ->first());
    }

    public function lockStudent(string $studentId): void
    {
        // Transaction-scoped: Postgres releases it at commit or rollback.
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ['quiz-start:'.$studentId]);
    }

    public function add(Attempt $attempt): void
    {
        DB::transaction(function () use ($attempt): void {
            QuizAttemptModel::query()->create([
                'id' => $attempt->id()->value(),
                'student_id' => $attempt->studentId(),
                'topic_id' => $attempt->topicId(),
                'subject_id' => $attempt->subjectId(),
                'started_at' => $attempt->startedAt(),
                'completed_at' => $attempt->completedAt(),
            ]);

            foreach ($attempt->questions() as $question) {
                QuizAttemptQuestionModel::query()->create([
                    'id' => $question->id()->value(),
                    'attempt_id' => $attempt->id()->value(),
                    'position' => $question->position(),
                    'question_id' => $question->questionId(),
                    'type' => $question->type(),
                    'statement' => $question->statement(),
                    'explanation' => $question->explanation(),
                    'options' => $this->mapper->optionsToJson($question),
                    'correct_option_id' => $question->correctOptionId(),
                ]);
            }
        });
    }

    public function saveAnswer(Attempt $attempt): void
    {
        $question = $attempt->lastAnswered();

        if ($question === null) {
            return;
        }

        DB::transaction(function () use ($attempt, $question): void {
            $chosen = $question->chosenOptionId();

            // "Not answered yet" is part of the write: two racing answers cannot both land.
            $updated = QuizAttemptQuestionModel::query()
                ->whereKey($question->id()->value())
                ->whereNull('answer_id')
                ->update([
                    'answer_id' => $question->answerId()?->value(),
                    'chosen_option_id' => $chosen,
                    // The bank option may have been removed by an edit: link only while it exists.
                    'option_id' => $chosen !== null && DB::table('question_options')->where('id', $chosen)->exists() ? $chosen : null,
                    'is_correct' => $question->isCorrect(),
                    'answered_at' => $question->answeredAt(),
                ]);

            if ($updated === 0) {
                throw new QuestionAlreadyAnsweredException;
            }

            if ($attempt->completedAt() !== null) {
                QuizAttemptModel::query()->whereKey($attempt->id()->value())->update(['completed_at' => $attempt->completedAt()]);
            }
        });
    }

    private function load(mixed $model): ?Attempt
    {
        if (! $model instanceof QuizAttemptModel) {
            return null;
        }

        $rows = QuizAttemptQuestionModel::query()->where('attempt_id', $model->id)->orderBy('position')->get();

        return $this->mapper->toDomain($model, array_values($rows->all()));
    }
}
