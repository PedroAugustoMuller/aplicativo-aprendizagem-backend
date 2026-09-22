<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity\Domain;

use App\Modules\Identity\Domain\Entity\User;
use App\Modules\Identity\Domain\ValueObject\Email;
use App\Modules\Identity\Domain\ValueObject\HashedPassword;
use App\Modules\Identity\Domain\ValueObject\UserId;
use App\Modules\Identity\Domain\ValueObject\Username;
use App\Shared\Domain\Auth\Role;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function test_staff_log_in_with_email_and_have_no_username(): void
    {
        $user = User::staff(UserId::random(), 'Ana', new Email('ana@escola.br'), $this->hash(), Role::Teacher, false);

        self::assertSame('ana@escola.br', $user->login());
        self::assertNull($user->username());
        self::assertSame(Role::Teacher, $user->role());
    }

    public function test_a_student_cannot_be_created_as_staff(): void
    {
        $this->expectException(InvalidArgumentException::class);
        User::staff(UserId::random(), 'Bia', new Email('bia@escola.br'), $this->hash(), Role::Student, false);
    }

    public function test_students_log_in_with_username_and_have_no_email(): void
    {
        $user = User::student(UserId::random(), 'Bia Lima', new Username('bia.lima'), $this->hash(), true);

        self::assertSame('bia.lima', $user->login());
        self::assertNull($user->email());
        self::assertSame(Role::Student, $user->role());
        self::assertTrue($user->mustChangePassword());
    }

    public function test_restore_rejects_a_student_with_an_email(): void
    {
        $this->expectException(InvalidArgumentException::class);
        User::restore(UserId::random(), 'X', Role::Student, new Email('x@escola.br'), new Username('x'), $this->hash(), false, null);
    }

    public function test_restore_rejects_staff_without_an_email(): void
    {
        $this->expectException(InvalidArgumentException::class);
        User::restore(UserId::random(), 'X', Role::Admin, null, null, $this->hash(), false, null);
    }

    public function test_deactivation_and_reactivation(): void
    {
        $user = User::student(UserId::random(), 'Bia', new Username('bia'), $this->hash(), false);
        $at = new DateTimeImmutable('2026-09-17 10:00:00');

        $user->deactivate($at);
        self::assertFalse($user->isActive());
        self::assertEquals($at, $user->deactivatedAt());

        $user->reactivate();
        self::assertTrue($user->isActive());
    }

    public function test_a_reset_forces_a_change_and_a_change_clears_it(): void
    {
        $user = User::student(UserId::random(), 'Bia', new Username('bia'), $this->hash(), false);

        $user->resetPassword(new HashedPassword('$2y$04$reset'));
        self::assertTrue($user->mustChangePassword());
        self::assertSame('$2y$04$reset', $user->password()->value());

        $user->changePassword(new HashedPassword('$2y$04$chosen'));
        self::assertFalse($user->mustChangePassword());
    }

    private function hash(): HashedPassword
    {
        return new HashedPassword('$2y$04$realhashrealhashrealha');
    }
}
