<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity\Application;

use App\Modules\Identity\Application\Command\CreateTeacher\CreateTeacherCommand;
use App\Modules\Identity\Application\Command\CreateTeacher\CreateTeacherHandler;
use App\Modules\Identity\Application\Service\PasswordIssuer;
use App\Modules\Identity\Domain\Entity\User;
use App\Modules\Identity\Domain\Exception\AccessDeniedException;
use App\Modules\Identity\Domain\Exception\EmailAlreadyTakenException;
use App\Modules\Identity\Domain\Service\TemporaryPasswordGenerator;
use App\Modules\Identity\Domain\ValueObject\Email;
use App\Modules\Identity\Domain\ValueObject\HashedPassword;
use App\Modules\Identity\Domain\ValueObject\UserId;
use App\Shared\Domain\Auth\Actor;
use App\Shared\Domain\Auth\Role;
use App\Shared\Domain\Exception\IdempotencyConflictException;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Modules\Identity\Support\IdentityFakes;
use Tests\Unit\Modules\Identity\Support\InMemoryCredentialVault;
use Tests\Unit\Modules\Identity\Support\InMemoryUserRepository;

final class CreateTeacherHandlerTest extends TestCase
{
    use IdentityFakes;

    private const ID = '0199c0de-1a2b-7c3d-8e4f-5a6b7c8d9e0f';

    public function test_an_admin_creates_a_teacher_with_a_temporary_password(): void
    {
        $users = $this->users();
        $vault = $this->vault();

        $view = $this->handler($users, $vault)
            ->handle(new CreateTeacherCommand($this->admin(), self::ID, 'Ana', 'ana@escola.br'));

        self::assertSame(self::ID, $view->id);
        self::assertSame('teacher', $view->role);
        self::assertTrue($view->mustChangePassword);
        self::assertNotNull($view->temporaryPassword);
        self::assertSame(8, strlen($view->temporaryPassword));
        self::assertSame($view->temporaryPassword, $vault->reveal(new UserId(self::ID)));
    }

    public function test_a_teacher_actor_cannot_create_a_teacher(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->handler($this->users(), $this->vault())
            ->handle(new CreateTeacherCommand(new Actor('t', Role::Teacher), self::ID, 'Ana', 'ana@escola.br'));
    }

    public function test_an_email_taken_by_another_user_is_rejected(): void
    {
        $existing = User::staff(UserId::random(), 'Beto', new Email('ana@escola.br'), new HashedPassword('hash:x'), Role::Teacher, false);
        $users = $this->users($existing);

        try {
            $this->handler($users, $this->vault())
                ->handle(new CreateTeacherCommand($this->admin(), self::ID, 'Ana', 'ana@escola.br'));
            self::fail('Expected EmailAlreadyTakenException.');
        } catch (EmailAlreadyTakenException $e) {
            self::assertSame(['email' => 'ana@escola.br'], $e->params());
        }
    }

    public function test_replaying_the_same_id_and_payload_returns_the_same_temporary_password(): void
    {
        $users = $this->users();
        $vault = $this->vault();
        $handler = $this->handler($users, $vault);
        $first = $handler->handle(new CreateTeacherCommand($this->admin(), self::ID, 'Ana', 'ana@escola.br'));

        $second = $handler->handle(new CreateTeacherCommand($this->admin(), self::ID, 'Ana', 'ana@escola.br'));

        self::assertSame($first->temporaryPassword, $second->temporaryPassword);
    }

    public function test_replaying_the_same_id_with_a_different_email_conflicts(): void
    {
        $users = $this->users();
        $handler = $this->handler($users, $this->vault());
        $handler->handle(new CreateTeacherCommand($this->admin(), self::ID, 'Ana', 'ana@escola.br'));

        $this->expectException(IdempotencyConflictException::class);
        $handler->handle(new CreateTeacherCommand($this->admin(), self::ID, 'Ana', 'outro@escola.br'));
    }

    public function test_replaying_after_the_teacher_changed_password_returns_no_temporary_password(): void
    {
        $users = $this->users();
        $vault = $this->vault();
        $handler = $this->handler($users, $vault);
        $handler->handle(new CreateTeacherCommand($this->admin(), self::ID, 'Ana', 'ana@escola.br'));

        $created = $users->findById(new UserId(self::ID));
        self::assertNotNull($created);
        $created->changePassword(new HashedPassword('hash:mine'));
        $users->save($created);
        $vault->forget($created->id());

        $again = $handler->handle(new CreateTeacherCommand($this->admin(), self::ID, 'Ana', 'ana@escola.br'));

        self::assertNull($again->temporaryPassword);
    }

    private function handler(InMemoryUserRepository $users, InMemoryCredentialVault $vault): CreateTeacherHandler
    {
        return new CreateTeacherHandler(
            $users,
            new PasswordIssuer($this->hasher(), $vault, new TemporaryPasswordGenerator),
            $vault,
            $this->transactions(),
        );
    }

    private function admin(): Actor
    {
        return new Actor('a', Role::Admin);
    }
}
