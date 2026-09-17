<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity\Domain;

use App\Modules\Identity\Domain\Entity\User;
use App\Modules\Identity\Domain\Exception\InvalidCredentialsException;
use App\Modules\Identity\Domain\ValueObject\Email;
use App\Modules\Identity\Domain\ValueObject\HashedPassword;
use App\Modules\Identity\Domain\ValueObject\UserId;
use App\Shared\Domain\Exception\DomainException;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function test_it_exposes_its_identity(): void
    {
        $id = UserId::random();
        $user = new User($id, 'Professora Ana', new Email('ana@escola.br'), new HashedPassword('$2y$04$abcdefghijklmnopqrstuv'));

        self::assertTrue($id->equals($user->id()));
        self::assertSame('Professora Ana', $user->name());
        self::assertSame('ana@escola.br', $user->email()->value());
    }

    public function test_invalid_credentials_is_a_401_domain_exception(): void
    {
        $exception = new InvalidCredentialsException;

        self::assertInstanceOf(DomainException::class, $exception);
        self::assertSame(401, $exception->status());
        self::assertSame('identity.invalid_credentials', $exception->errorCode());
        self::assertSame([], $exception->params());
    }
}
