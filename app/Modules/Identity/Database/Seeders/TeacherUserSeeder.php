<?php

declare(strict_types=1);

namespace App\Modules\Identity\Database\Seeders;

use App\Modules\Identity\Domain\ValueObject\UserId;
use App\Modules\Identity\Infrastructure\Persistence\UserModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class TeacherUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = UserModel::query()->firstOrNew(['email' => 'ana@escola.br']);

        // Assign the id only on creation. Putting it in updateOrCreate's update
        // values rotates the primary key on every reseed, which silently orphans
        // any Sanctum token already issued against the old id — tokenable_id has
        // no foreign key, so nothing fails loudly; the user is simply logged out
        // with no explanation.
        if (! $user->exists) {
            $user->setAttribute('id', UserId::random()->value());
        }

        $user->fill([
            'name' => 'Professora Ana',
            'password' => Hash::make('password'),
        ])->save();
    }
}
