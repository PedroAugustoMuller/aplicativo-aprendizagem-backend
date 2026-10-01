<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Identity;

use App\Modules\Identity\Domain\ValueObject\UserId;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class UsersRoleMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_database_rejects_a_student_with_an_email(): void
    {
        $this->expectException(QueryException::class);

        DB::table('users')->insert([
            'id' => UserId::random()->value(), 'name' => 'X', 'role' => 'student',
            'email' => 'x@escola.br', 'username' => 'x', 'password' => 'h',
        ]);
    }

    public function test_the_database_rejects_staff_without_an_email(): void
    {
        $this->expectException(QueryException::class);

        DB::table('users')->insert([
            'id' => UserId::random()->value(), 'name' => 'X', 'role' => 'teacher',
            'email' => null, 'username' => null, 'password' => 'h',
        ]);
    }

    public function test_the_database_rejects_an_unknown_role(): void
    {
        $this->expectException(QueryException::class);

        DB::table('users')->insert([
            'id' => UserId::random()->value(), 'name' => 'X', 'role' => 'janitor',
            'email' => 'x@escola.br', 'password' => 'h',
        ]);
    }

    public function test_the_database_rejects_a_row_with_no_role(): void
    {
        $this->expectException(QueryException::class);

        DB::table('users')->insert([
            'id' => UserId::random()->value(), 'name' => 'X',
            'email' => 'x@escola.br', 'password' => 'h',
        ]);
    }
}
