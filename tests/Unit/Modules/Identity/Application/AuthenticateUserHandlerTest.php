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
use App\Modules\Identity\Domain\ValueObject\Username;
use App\Shared\Domain\Auth\Role;
use PHPUnit\Framework\TestCase;

final class AuthenticateUserHandlerTest extends TestCase
{
    public function test_a_teacher_signs_in_with_email(): void
    {
        $user = User::staff(UserId::random(), 'Professora Ana', new Email('ana@escola.br'), $this->hash(), Role::Teacher, false);

        $result = $this->handler($user, verifies: true)->handle(new AuthenticateUserCommand('ana@escola.br', 'password'));

        self::assertSame($user->id()->value(), $result->id);
        self::assertSame('ana@escola.br', $result->login);
        self::assertSame('teacher', $result->role);
        self::assertFalse($result->mustChangePassword);
        self::assertSame('issued-token', $result->token);
    }

    public function test_a_student_signs_in_with_username_and_is_told_to_change_password(): void
    {
        $user = User::student(UserId::random(), 'Bia Lima', new Username('bia.lima'), $this->hash(), true);

        $result = $this->handler($user, verifies: true)->handle(new AuthenticateUserCommand('Bia.Lima', 'temp'));

        self::assertSame('bia.lima', $result->login);
        self::assertSame('student', $result->role);
        self::assertTrue($result->mustChangePassword);
    }

    public function test_a_deactivated_account_is_indistinguishable_from_a_wrong_password(): void
    {
        $user = User::student(UserId::random(), 'Bia', new Username('bia'), $this->hash(), false);
        $user->deactivate(new \DateTimeImmutable);

        $this->expectException(InvalidCredentialsException::class);

        $this->handler($user, verifies: true)->handle(new AuthenticateUserCommand('bia', 'right-password'));
    }

    public function test_it_rejects_a_wrong_password(): void
    {
        $user = User::staff(UserId::random(), 'Ana', new Email('ana@escola.br'), $this->hash(), Role::Admin, false);

        $this->expectException(InvalidCredentialsException::class);

        $this->handler($user, verifies: false)->handle(new AuthenticateUserCommand('ana@escola.br', 'wrong'));
    }

    public function test_it_rejects_an_unknown_login(): void
    {
        $this->expectException(InvalidCredentialsException::class);

        $this->handler(null, verifies: false)->handle(new AuthenticateUserCommand('nobody', 'password'));
    }

    public function test_it_rejects_a_login_that_is_neither_email_nor_username_without_a_crash(): void
    {
        $this->expectException(InvalidCredentialsException::class);

        $this->handler(null, verifies: false)->handle(new AuthenticateUserCommand('not valid!', 'password'));
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

            public function hash(string $plain): HashedPassword
            {
                return new HashedPassword('$2y$04$dummydummydummydummydu');
            }
        };

        try {
            (new AuthenticateUserHandler($this->repository(null), $hasher, $this->tokens()))
                ->handle(new AuthenticateUserCommand('nobody', 'password'));
        } catch (InvalidCredentialsException) {
            // expected
        }

        self::assertSame(1, $hasher->calls, 'Skipping the hash for unknown logins leaks account existence by timing.');
    }

    public function test_it_hashes_even_when_the_account_is_deactivated(): void
    {
        $user = User::student(UserId::random(), 'Bia', new Username('bia'), $this->hash(), false);
        $user->deactivate(new \DateTimeImmutable);

        $hasher = new class implements PasswordHasher
        {
            public int $calls = 0;

            public function verify(string $plain, HashedPassword $hashed): bool
            {
                $this->calls++;

                return true;
            }

            public function dummyHash(): HashedPassword
            {
                return new HashedPassword('$2y$04$dummydummydummydummydu');
            }

            public function hash(string $plain): HashedPassword
            {
                return new HashedPassword('$2y$04$dummydummydummydummydu');
            }
        };

        try {
            (new AuthenticateUserHandler($this->repository($user), $hasher, $this->tokens()))
                ->handle(new AuthenticateUserCommand('bia', 'right-password'));
        } catch (InvalidCredentialsException) {
            // expected
        }

        self::assertSame(1, $hasher->calls, 'Skipping the hash for deactivated accounts leaks account status by timing.');
    }

    private function handler(?User $user, bool $verifies): AuthenticateUserHandler
    {
        return new AuthenticateUserHandler($this->repository($user), $this->hasher($verifies), $this->tokens());
    }

    private function hash(): HashedPassword
    {
        return new HashedPassword('$2y$04$realhashrealhashrealha');
    }

    private function repository(?User $user): UserRepository
    {
        return new class($user) implements UserRepository
        {
            public function __construct(private readonly ?User $user) {}

            public function findById(UserId $id): ?User
            {
                return $this->user;
            }

            public function findByEmail(Email $email): ?User
            {
                return $this->user?->email()?->equals($email) === true ? $this->user : null;
            }

            public function findByUsername(Username $username): ?User
            {
                return $this->user?->username()?->value() === $username->value() ? $this->user : null;
            }

            public function emailExists(Email $email): bool
            {
                return false;
            }

            public function usernameExists(string $username): bool
            {
                return false;
            }

            public function save(User $user): void {}
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

            public function hash(string $plain): HashedPassword
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
