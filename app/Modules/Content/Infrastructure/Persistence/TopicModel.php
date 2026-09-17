<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * Persistence detail. Holds no business rules — those live in the domain entity.
 *
 * @property string $id
 * @property string $name
 * @property string $description
 * @property int $position
 */
final class TopicModel extends Model
{
    protected $table = 'topics';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['position' => 'integer'];
    }
}
