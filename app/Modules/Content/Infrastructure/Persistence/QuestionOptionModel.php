<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $question_id
 * @property string $text
 * @property bool $is_correct
 * @property int $position
 */
final class QuestionOptionModel extends Model
{
    protected $table = 'question_options';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_correct' => 'boolean', 'position' => 'integer'];
    }
}
