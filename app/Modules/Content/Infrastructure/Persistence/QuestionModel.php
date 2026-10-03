<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * Persistence detail. Holds no business rules — those live in the domain entity.
 *
 * @property string $id
 * @property string $topic_id
 * @property string $type
 * @property string $statement
 * @property string|null $explanation
 * @property int $version
 * @property \DateTimeImmutable|null $deactivated_at
 */
final class QuestionModel extends Model
{
    protected $table = 'questions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['version' => 'integer', 'deactivated_at' => 'immutable_datetime'];
    }
}
