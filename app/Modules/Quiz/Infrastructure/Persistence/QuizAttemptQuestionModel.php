<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $attempt_id
 * @property int $position
 * @property string|null $question_id
 * @property string $type
 * @property string $statement
 * @property string|null $explanation
 * @property mixed $options
 * @property string $correct_option_id
 * @property string|null $answer_id
 * @property string|null $chosen_option_id
 * @property string|null $option_id
 * @property bool|null $is_correct
 * @property \DateTimeImmutable|null $answered_at
 */
final class QuizAttemptQuestionModel extends Model
{
    protected $table = 'quiz_attempt_questions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'options' => 'array',
            'is_correct' => 'boolean',
            'answered_at' => 'immutable_datetime',
        ];
    }
}
