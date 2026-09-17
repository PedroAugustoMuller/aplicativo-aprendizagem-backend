<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity\Application;

use App\Modules\Identity\Application\Command\AuthenticateUser\AuthenticateUserCommand;
use App\Modules\Identity\Application\Command\AuthenticateUser\AuthenticateUserHandler;
use App\Modules\Identity\Application\Port\PasswordHasher;
use App\Modules\Identity\Application\Port\TokenIssuer;
use App\Modules\Identity\Domain\Entity\User;
use App\Modules\Identity\Domain\Exception\InvalidCredentialsException;
use App\Modules\Identity\Domain\Repository\UserRepository;
use App\Modules\Identity\Domain\ValueObject\Email;
use App\Modules\Identity\Domain\ValueObject\HashedPassword;
use App\Modules\Identity\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class AuthenticateUserHandlerTest extends TestCase
{
    public function test_it_issues_a_token_for_correct_credentials(): void
    {
        $id = UserId::random();
        $handler = $this->handler($this->user($id), verifies: true);

        $result = $handler->handle(new AuthenticateUserCommand('ana@escola.br', 'password'));

        self::assertSame($id->value(), $result->id);
        self::assertSame('Professora Ana', $result->name);
        self::assertSame('ana@escola.br', $result->email);
        self::assertSame('issued-token', $result->token);
    }

    public function test_it_rejects_a_wrong_password(): void
    {
        $handler = $this->handler($this->user(UserId::random()), verifies: false);

        $this->expectException(InvalidCredentialsException::class);

        $handler->handle(new AuthenticateUserCommand('ana@escola.br', 'wrong'));
    }

    public function test_it_rejects_an_unknown_email(): void
    {
        $handler = $this->handler(null, verifies: false);

        $this->expectException(InvalidCredentialsException::class);

        $handler->handle(new AuthenticateUserCommand('nobody@escola.br', 'password'));
    }

    public function test_it_rejects_a_malformed_email_without_a_crash(): void
    {
        $handler = $this->handler(null, verifies: false);

        $this->expectException(InvalidCredentialsException::class);

        $handler->handle(new AuthenticateUserCommand('not-an-email', 'password'));
    }

    public function test_it_hashes_even_when_the_user_is_unknown(): void
    {
        $hasher = new class implements PasswordHasher
        {
            public int $calls = 0;

            public function verify(string $plain, HashedPassword $hashed): bool
            {
                $this->calls++;

                return false;
            }

            public function dummyHash(): HashedPassword
            {
                return new HashedPassword('$2y$04$dummydummydummydummydu');
            }
        };

        try {
            (new AuthenticateUserHandler($this->repository(null), $hasher, $this->tokens()))
                ->handle(new AuthenticateUserCommand('nobody@escola.br', 'password'));
        } catch (InvalidCredentialsException) {
            // expected
        }

        self::assertSame(1, $hasher->calls, 'Skipping the hash for unknown emails leaks account existence by timing.');
    }

    private function handler(?User $user, bool $verifies): AuthenticateUserHandler
    {
        return new AuthenticateUserHandler($this->repository($user), $this->hasher($verifies), $this->tokens());
    }

    private function user(UserId $id): User
    {
        return new User($id, 'Professora Ana', new Email('ana@escola.br'), new HashedPassword('$2y$04$realhashrealhashrealha'));
    }

    private function repository(?User $user): UserRepository
    {
        return new class($user) implements UserRepository
        {
            public function __construct(private readonly ?User $user) {}

            public function findByEmail(Email $email): ?User
            {
                return $this->user;
            }
        };
    }

    private function hasher(bool $verifies): PasswordHasher
    {
        return new class($verifies) implements PasswordHasher
        {
            public function __construct(private readonly bool $verifies) {}

            public function verify(string $plain, HashedPassword $hashed): bool
            {
                return $this->verifies;
            }

            public function dummyHash(): HashedPassword
            {
                return new HashedPassword('$2y$04$dummydummydummydummydu');
            }
        };
    }

    private function tokens(): TokenIssuer
    {
        return new class implements TokenIssuer
        {
            public function issue(UserId $userId): string
            {
                return 'issued-token';
            }
        };
    }
}
