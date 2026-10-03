<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $student_id
 * @property string $topic_id
 * @property string $subject_id
 * @property \DateTimeImmutable $started_at
 * @property \DateTimeImmutable|null $completed_at
 */
final class QuizAttemptModel extends Model
{
    protected $table = 'quiz_attempts';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['started_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime'];
    }
}
