<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Contract;

use App\Modules\Content\Infrastructure\Persistence\QuestionModel;
use App\Modules\Content\Infrastructure\Persistence\QuestionOptionModel;
use App\Modules\Content\Infrastructure\Persistence\SubjectModel;
use App\Modules\Content\Infrastructure\Persistence\TopicModel;
use App\Shared\Domain\Contract\BankOption;
use App\Shared\Domain\Contract\BankQuestion;
use App\Shared\Domain\Contract\BankTopic;
use App\Shared\Domain\Contract\QuestionBank;
use Illuminate\Support\Str;

/**
 * Implements Shared's QuestionBank on top of Content's own persistence, so the quiz
 * can draw questions without importing Content's internals.
 */
final class EloquentQuestionBank implements QuestionBank
{
    public function topic(string $topicId): ?BankTopic
    {
        // Postgres rejects a malformed uuid literal instead of finding nothing.
        if (! Str::isUuid($topicId)) {
            return null;
        }

        $topic = TopicModel::query()->find($topicId);

        if (! $topic instanceof TopicModel) {
            return null;
        }

        $subjectActive = SubjectModel::query()->whereKey($topic->subject_id)->whereNull('deactivated_at')->exists();

        return new BankTopic($topic->id, $topic->subject_id, $subjectActive && $topic->deactivated_at === null);
    }

    public function activeQuestions(string $topicId): array
    {
        if (! Str::isUuid($topicId)) {
            return [];
        }

        $questions = QuestionModel::query()
            ->where('topic_id', $topicId)
            ->whereNull('deactivated_at')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        if ($questions->isEmpty()) {
            return [];
        }

        /** @var array<string, list<BankOption>> $optionsByQuestion */
        $optionsByQuestion = [];
        $options = QuestionOptionModel::query()
            ->whereIn('question_id', $questions->modelKeys())
            ->orderBy('position')
            ->get();

        foreach ($options as $option) {
            $optionsByQuestion[$option->question_id][] = new BankOption($option->id, $option->text, $option->is_correct);
        }

        $bank = [];

        foreach ($questions as $question) {
            $bank[] = new BankQuestion(
                $question->id,
                $question->type,
                $question->statement,
                $question->explanation,
                $optionsByQuestion[$question->id] ?? [],
            );
        }

        return $bank;
    }
}
