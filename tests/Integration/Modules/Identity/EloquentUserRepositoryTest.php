<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Identity;

use App\Modules\Identity\Domain\Repository\UserRepository;
use App\Modules\Identity\Domain\ValueObject\Email;
use App\Modules\Identity\Domain\ValueObject\UserId;
use App\Modules\Identity\Infrastructure\Persistence\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EloquentUserRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_finds_a_user_by_email_and_maps_it_to_the_domain(): void
    {
        $id = UserId::random();

        UserModel::query()->create([
            'id' => $id->value(),
            'name' => 'Professora Ana',
            'email' => 'ana@escola.br',
            'password' => bcrypt('password'),
        ]);

        $user = $this->repository()->findByEmail(new Email('ana@escola.br'));

        self::assertNotNull($user);
        self::assertTrue($id->equals($user->id()));
        self::assertSame('Professora Ana', $user->name());
        self::assertSame('ana@escola.br', $user->email()->value());
        self::assertNotSame('password', $user->password()->value());
    }

    public function test_lookup_is_case_insensitive_because_email_normalises(): void
    {
        UserModel::query()->create([
            'id' => UserId::random()->value(),
            'name' => 'Professora Ana',
            'email' => 'ana@escola.br',
            'password' => bcrypt('password'),
        ]);

        self::assertNotNull($this->repository()->findByEmail(new Email('ANA@Escola.BR')));
    }

    public function test_it_returns_null_for_an_unknown_email(): void
    {
        self::assertNull($this->repository()->findByEmail(new Email('nobody@escola.br')));
    }

    private function repository(): UserRepository
    {
        return $this->app->make(UserRepository::class);
    }
}
