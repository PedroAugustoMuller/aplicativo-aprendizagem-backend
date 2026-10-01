<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Modules\Identity\Domain\ValueObject\UserId;
use App\Modules\Identity\Infrastructure\Persistence\UserModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** Shared user/token/subject/classroom fixtures for feature tests, used from here on. */
trait ActsAsUsers
{
    protected function makeUser(string $role, string $name = 'User', bool $mustChange = false): UserModel
    {
        $id = UserId::random()->value();
        $short = substr(str_replace('-', '', $id), -10);

        return UserModel::query()->create([
            'id' => $id,
            'name' => $name,
            'role' => $role,
            'email' => $role === 'student' ? null : $short.'@escola.br',
            'username' => $role === 'student' ? 'u'.$short : null,
            'password' => Hash::make('password'),
            'must_change_password' => $mustChange,
        ]);
    }

    protected function tokenFor(UserModel $user): string
    {
        return $user->createToken('api', ['*'], now()->addDay())->plainTextToken;
    }

    protected function makeSubject(string $name = 'Química'): string
    {
        $id = (string) Str::uuid7();
        DB::table('subjects')->insert(['id' => $id, 'name' => $name, 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    /**
     * @param  list<UserModel>  $teachers
     * @param  list<UserModel>  $students
     */
    protected function makeClassroom(string $subjectId, array $teachers = [], array $students = [], string $name = 'Química 1'): string
    {
        $id = (string) Str::uuid7();
        DB::table('classrooms')->insert(['id' => $id, 'name' => $name, 'subject_id' => $subjectId, 'created_at' => now(), 'updated_at' => now()]);
        foreach ($teachers as $t) {
            DB::table('classroom_teachers')->insert(['classroom_id' => $id, 'user_id' => $t->getKey()]);
        }
        foreach ($students as $s) {
            DB::table('classroom_students')->insert(['classroom_id' => $id, 'user_id' => $s->getKey()]);
        }

        return $id;
    }
}
