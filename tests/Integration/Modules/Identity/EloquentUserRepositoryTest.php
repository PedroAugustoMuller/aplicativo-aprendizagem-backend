<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Identity;

use App\Modules\Identity\Domain\Entity\User;
use App\Modules\Identity\Domain\Repository\UserRepository;
use App\Modules\Identity\Domain\ValueObject\Email;
use App\Modules\Identity\Domain\ValueObject\HashedPassword;
use App\Modules\Identity\Domain\ValueObject\UserId;
use App\Modules\Identity\Domain\ValueObject\Username;
use App\Shared\Domain\Auth\Role;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EloquentUserRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_finds_a_user_by_email_and_maps_it_to_the_domain(): void
    {
        $id = UserId::random();

        $this->repository()->save(User::staff($id, 'Professora Ana', new Email('ana@escola.br'), new HashedPassword(bcrypt('password')), Role::Teacher, false));

        $user = $this->repository()->findByEmail(new Email('ana@escola.br'));

        self::assertNotNull($user);
        self::assertTrue($id->equals($user->id()));
        self::assertSame('Professora Ana', $user->name());
        self::assertSame('ana@escola.br', $user->email()?->value());
        self::assertNotSame('password', $user->password()->value());
    }

    public function test_lookup_is_case_insensitive_because_email_normalises(): void
    {
        $this->repository()->save(User::staff(UserId::random(), 'Professora Ana', new Email('ana@escola.br'), new HashedPassword(bcrypt('password')), Role::Teacher, false));

        self::assertNotNull($this->repository()->findByEmail(new Email('ANA@Escola.BR')));
    }

    public function test_it_returns_null_for_an_unknown_email(): void
    {
        self::assertNull($this->repository()->findByEmail(new Email('nobody@escola.br')));
    }

    public function test_a_student_round_trips_through_save_and_find_by_username(): void
    {
        $id = UserId::random();
        $this->repository()->save(User::student($id, 'Bia Lima', new Username('bia.lima'), new HashedPassword(bcrypt('x')), true));

        $found = $this->repository()->findByUsername(new Username('BIA.LIMA'));

        self::assertNotNull($found);
        self::assertTrue($id->equals($found->id()));
        self::assertSame(Role::Student, $found->role());
        self::assertNull($found->email());
        self::assertTrue($found->mustChangePassword());
        self::assertTrue($this->repository()->usernameExists('bia.lima'));
        self::assertFalse($this->repository()->usernameExists('bia.lima2'));
    }

    public function test_deactivation_persists(): void
    {
        $user = User::staff(UserId::random(), 'Ana', new Email('ana@escola.br'), new HashedPassword(bcrypt('x')), Role::Teacher, false);
        $user->deactivate(new DateTimeImmutable('2026-09-17 10:00:00'));
        $this->repository()->save($user);

        $found = $this->repository()->findById($user->id());

        self::assertNotNull($found);
        self::assertFalse($found->isActive());
    }

    private function repository(): UserRepository
    {
        return $this->app->make(UserRepository::class);
    }
}
