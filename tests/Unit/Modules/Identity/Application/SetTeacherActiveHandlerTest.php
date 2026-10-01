<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity\Application;

use App\Modules\Identity\Application\Command\SetTeacherActive\SetTeacherActiveCommand;
use App\Modules\Identity\Application\Command\SetTeacherActive\SetTeacherActiveHandler;
use App\Modules\Identity\Domain\Entity\User;
use App\Modules\Identity\Domain\Exception\AccessDeniedException;
use App\Modules\Identity\Domain\Exception\TeacherNotFoundException;
use App\Modules\Identity\Domain\ValueObject\Email;
use App\Modules\Identity\Domain\ValueObject\HashedPassword;
use App\Modules\Identity\Domain\ValueObject\UserId;
use App\Modules\Identity\Domain\ValueObject\Username;
use App\Shared\Domain\Auth\Actor;
use App\Shared\Domain\Auth\Role;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Modules\Identity\Support\IdentityFakes;

final class SetTeacherActiveHandlerTest extends TestCase
{
    use IdentityFakes;

    public function test_an_admin_deactivates_a_teacher_and_revokes_their_tokens(): void
    {
        $teacher = $this->teacher();
        $users = $this->users($teacher);
        $revoker = $this->revoker();

        $view = (new SetTeacherActiveHandler($users, $revoker, $this->transactions()))
            ->handle(new SetTeacherActiveCommand($this->admin(), $teacher->id()->value(), false));

        self::assertFalse($view->active);
        self::assertContains($teacher->id()->value(), $revoker->allCalls);
    }

    public function test_an_admin_reactivates_a_teacher_without_revoking_tokens(): void
    {
        $teacher = $this->teacher();
        $teacher->deactivate(new DateTimeImmutable);
        $users = $this->users($teacher);
        $revoker = $this->revoker();

        $view = (new SetTeacherActiveHandler($users, $revoker, $this->transactions()))
            ->handle(new SetTeacherActiveCommand($this->admin(), $teacher->id()->value(), true));

        self::assertTrue($view->active);
        self::assertSame([], $revoker->allCalls);
    }

    public function test_an_admin_cannot_deactivate_themself(): void
    {
        $admin = User::staff(UserId::random(), 'Root', new Email('root@escola.br'), $this->hash(), Role::Admin, false);
        $users = $this->users($admin);

        $this->expectException(AccessDeniedException::class);

        (new SetTeacherActiveHandler($users, $this->revoker(), $this->transactions()))
            ->handle(new SetTeacherActiveCommand(new Actor($admin->id()->value(), Role::Admin), $admin->id()->value(), false));
    }

    public function test_a_student_id_is_not_found(): void
    {
        $student = User::student(UserId::random(), 'Bia', new Username('bia'), $this->hash(), false);
        $users = $this->users($student);

        $this->expectException(TeacherNotFoundException::class);

        (new SetTeacherActiveHandler($users, $this->revoker(), $this->transactions()))
            ->handle(new SetTeacherActiveCommand($this->admin(), $student->id()->value(), false));
    }

    private function teacher(): User
    {
        return User::staff(UserId::random(), 'Ana', new Email('ana@escola.br'), $this->hash(), Role::Teacher, false);
    }

    private function hash(): HashedPassword
    {
        return new HashedPassword('$2y$04$x');
    }

    private function admin(): Actor
    {
        return new Actor('a', Role::Admin);
    }
}
