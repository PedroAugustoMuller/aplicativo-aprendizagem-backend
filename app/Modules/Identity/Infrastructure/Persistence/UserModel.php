<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Persistence;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Persistence detail. Holds no business rules — those live in the domain entity.
 */
final class UserModel extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'users';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['password'];
}
