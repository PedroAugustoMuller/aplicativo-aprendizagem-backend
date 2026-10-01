<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Identity;

use App\Modules\Identity\Application\Port\CredentialVault;
use App\Modules\Identity\Domain\Entity\User;
use App\Modules\Identity\Domain\Repository\UserRepository;
use App\Modules\Identity\Domain\ValueObject\HashedPassword;
use App\Modules\Identity\Domain\ValueObject\UserId;
use App\Modules\Identity\Domain\ValueObject\Username;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class EncryptedCredentialVaultTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_encrypted_and_reveals_plain(): void
    {
        $id = $this->student('bia');

        $this->vault()->store($id, 'Kx7mPq2r');

        $raw = DB::table('pending_credentials')->where('user_id', $id->value())->value('password_encrypted');
        self::assertIsString($raw);
        self::assertStringNotContainsString('Kx7mPq2r', $raw);
        self::assertSame('Kx7mPq2r', $this->vault()->reveal($id));
    }

    public function test_storing_again_replaces_and_forget_removes(): void
    {
        $id = $this->student('bia');

        $this->vault()->store($id, 'first111');
        $this->vault()->store($id, 'second22');
        self::assertSame('second22', $this->vault()->reveal($id));

        $this->vault()->forget($id);
        self::assertNull($this->vault()->reveal($id));
    }

    public function test_reveal_many_skips_users_without_a_pending_credential(): void
    {
        $a = $this->student('ana');
        $b = $this->student('bia');
        $this->vault()->store($a, 'aaaaaaaa');

        self::assertSame([$a->value() => 'aaaaaaaa'], $this->vault()->revealMany([$a, $b]));
    }

    private function student(string $username): UserId
    {
        $id = UserId::random();
        $this->app->make(UserRepository::class)->save(
            User::student($id, ucfirst($username), new Username($username), new HashedPassword('$2y$04$x'), true),
        );

        return $id;
    }

    private function vault(): CredentialVault
    {
        return $this->app->make(CredentialVault::class);
    }
}
