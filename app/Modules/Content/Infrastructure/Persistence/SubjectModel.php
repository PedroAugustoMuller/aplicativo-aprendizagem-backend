<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * Persistence detail. Holds no business rules — those live in the domain entity.
 *
 * @property string $id
 * @property string $name
 * @property \DateTimeImmutable|null $deactivated_at
 */
final class SubjectModel extends Model
{
    protected $table = 'subjects';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['deactivated_at' => 'immutable_datetime'];
    }
}
