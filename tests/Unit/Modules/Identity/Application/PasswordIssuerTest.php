<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity\Application;

use App\Modules\Identity\Application\Service\PasswordIssuer;
use App\Modules\Identity\Domain\Entity\User;
use App\Modules\Identity\Domain\Service\TemporaryPasswordGenerator;
use App\Modules\Identity\Domain\ValueObject\Email;
use App\Modules\Identity\Domain\ValueObject\HashedPassword;
use App\Modules\Identity\Domain\ValueObject\UserId;
use App\Shared\Domain\Auth\Role;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Modules\Identity\Support\IdentityFakes;

final class PasswordIssuerTest extends TestCase
{
    use IdentityFakes;

    public function test_it_issues_forces_a_change_and_remembers(): void
    {
        $user = $this->teacher();
        $vault = $this->vault();
        $issuer = new PasswordIssuer($this->hasher(), $vault, new TemporaryPasswordGenerator);

        $issued = $issuer->issueFor($user);
        self::assertTrue($issued->isNew);
        self::assertSame('hash:'.$issued->plain, $user->password()->value());
        self::assertTrue($user->mustChangePassword());
        self::assertNull($vault->reveal($user->id()), 'issueFor must not write: the user row may not exist yet.');

        $issuer->remember($user, $issued);
        self::assertSame($issued->plain, $vault->reveal($user->id()));
    }

    public function test_a_second_issue_while_still_pending_returns_the_same_password(): void
    {
        $user = $this->teacher();
        $issuer = new PasswordIssuer($this->hasher(), $this->vault(), new TemporaryPasswordGenerator);
        $first = $issuer->issueFor($user);
        $issuer->remember($user, $first);

        $second = $issuer->issueFor($user);

        self::assertSame($first->plain, $second->plain);
        self::assertFalse($second->isNew);
    }

    public function test_once_the_user_chose_a_password_a_new_issue_generates_a_new_one(): void
    {
        $user = $this->teacher();
        $vault = $this->vault();
        $issuer = new PasswordIssuer($this->hasher(), $vault, new TemporaryPasswordGenerator);
        $first = $issuer->issueFor($user);
        $issuer->remember($user, $first);

        $user->changePassword(new HashedPassword('hash:mine'));
        $vault->forget($user->id());

        $second = $issuer->issueFor($user);
        self::assertTrue($second->isNew);
        self::assertNotSame($first->plain, $second->plain);
    }

    private function teacher(): User
    {
        return User::staff(UserId::random(), 'Ana', new Email('ana@escola.br'), new HashedPassword('hash:x'), Role::Teacher, false);
    }
}
