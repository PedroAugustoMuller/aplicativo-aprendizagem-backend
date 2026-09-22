<?php

declare(strict_types=1);

namespace App\Modules\Identity\Database\Seeders;

use App\Modules\Identity\Domain\ValueObject\UserId;
use App\Modules\Identity\Infrastructure\Persistence\UserModel;
use App\Shared\Domain\Auth\Role;
use App\Shared\Infrastructure\Persistence\EloquentAttribute;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * The development dataset the frontend e2e suite logs into. Fixed ids and the
 * exact logins/passwords below are a contract with that suite, not incidental
 * test data — do not change them without updating it.
 *
 * Safe to re-run: every row is upserted to the same state, and Diego's pending
 * credential is reissued on every run so "must change password" always has
 * something to exercise, even after a previous run made him change it.
 *
 * Local/testing only: this creates a live admin login (ana@escola.br /
 * password). Running it against production would both plant a predictable
 * credential and permanently block `identity:create-admin`, which refuses to
 * run once any admin exists. `testing` stays enabled because
 * DevelopmentSeedTest and RateLimitTest seed this class directly.
 */
final class DevelopmentAccountsSeeder extends Seeder
{
    private const BRUNO_ID = '0192f0a0-0000-7000-8000-000000000011';

    private const CARLA_ID = '0192f0a0-0000-7000-8000-000000000012';

    private const DIEGO_ID = '0192f0a0-0000-7000-8000-000000000013';

    private const CHEMISTRY_1_CLASSROOM_ID = '0192f0a0-0000-7000-8000-000000000021';

    private const DIEGO_TEMPORARY_PASSWORD = 'Temp2345';

    /**
     * Repeats App\Modules\Content\Database\Seeders\SubjectsSeeder::CHEMISTRY_ID.
     * Identity may not import Content (module boundary, enforced by deptrac), so
     * the value is duplicated here and must be kept in sync with that constant.
     */
    private const CHEMISTRY_SUBJECT_ID = '0192f0a0-0000-7000-8000-000000000001';

    public function run(Application $app): void
    {
        if (! $app->environment('local', 'testing')) {
            return;
        }

        $this->upsertAna();
        $brunoId = $this->upsertStaff(self::BRUNO_ID, 'Professor Bruno', 'bruno@escola.br', Role::Teacher);
        $carlaId = $this->upsertStudent(self::CARLA_ID, 'Carla Dias', 'carla.dias', 'password', mustChangePassword: false);
        $diegoId = $this->upsertStudent(self::DIEGO_ID, 'Diego Souza', 'diego.souza', self::DIEGO_TEMPORARY_PASSWORD, mustChangePassword: true);

        DB::table('classrooms')->updateOrInsert(
            ['id' => self::CHEMISTRY_1_CLASSROOM_ID],
            [
                'name' => 'Química 1',
                'subject_id' => self::CHEMISTRY_SUBJECT_ID,
                'deactivated_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        DB::table('classroom_teachers')->insertOrIgnore(['classroom_id' => self::CHEMISTRY_1_CLASSROOM_ID, 'user_id' => $brunoId]);
        DB::table('classroom_students')->insertOrIgnore(['classroom_id' => self::CHEMISTRY_1_CLASSROOM_ID, 'user_id' => $carlaId]);
        DB::table('classroom_students')->insertOrIgnore(['classroom_id' => self::CHEMISTRY_1_CLASSROOM_ID, 'user_id' => $diegoId]);

        // The vault, not the hash, is what makes the credential reprintable —
        // reissued every run regardless of whether the user row already existed.
        DB::table('pending_credentials')->updateOrInsert(
            ['user_id' => $diegoId],
            ['password_encrypted' => Crypt::encryptString(self::DIEGO_TEMPORARY_PASSWORD), 'created_at' => now()],
        );
    }

    /** Ana is the development admin (admins can do everything a teacher can). */
    private function upsertAna(): string
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
            'role' => Role::Admin->value,
            'password' => Hash::make('password'),
            'must_change_password' => false,
            'deactivated_at' => null,
        ])->save();

        return $this->keyOf($user);
    }

    private function upsertStaff(string $id, string $name, string $email, Role $role): string
    {
        $user = UserModel::query()->firstOrNew(['id' => $id]);

        $user->fill([
            'name' => $name,
            'role' => $role->value,
            'email' => $email,
            'username' => null,
            'password' => Hash::make('password'),
            'must_change_password' => false,
            'deactivated_at' => null,
        ])->save();

        return $this->keyOf($user);
    }

    private function upsertStudent(string $id, string $name, string $username, string $password, bool $mustChangePassword): string
    {
        $user = UserModel::query()->firstOrNew(['id' => $id]);

        $user->fill([
            'name' => $name,
            'role' => Role::Student->value,
            'email' => null,
            'username' => $username,
            'password' => Hash::make($password),
            'must_change_password' => $mustChangePassword,
            'deactivated_at' => null,
        ])->save();

        return $this->keyOf($user);
    }

    private function keyOf(UserModel $user): string
    {
        return EloquentAttribute::string($user->getKey(), 'users.id');
    }
}
