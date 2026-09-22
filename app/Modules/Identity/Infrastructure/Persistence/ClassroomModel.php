<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * Persistence detail. Holds no business rules — those live in the domain entity.
 *
 * @property string $id
 * @property string $name
 * @property string $subject_id
 * @property \DateTimeImmutable|null $deactivated_at
 */
final class ClassroomModel extends Model
{
    protected $table = 'classrooms';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['deactivated_at' => 'immutable_datetime'];
    }
}
