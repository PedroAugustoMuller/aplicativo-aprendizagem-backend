<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity\Application;

use App\Modules\Identity\Application\Command\ResetStudentPassword\ResetStudentPasswordCommand;
use App\Modules\Identity\Application\Command\ResetStudentPassword\ResetStudentPasswordHandler;
use App\Modules\Identity\Application\Service\PasswordIssuer;
use App\Modules\Identity\Domain\Entity\Classroom;
use App\Modules\Identity\Domain\Entity\User;
use App\Modules\Identity\Domain\Exception\AccessDeniedException;
use App\Modules\Identity\Domain\Exception\StudentNotFoundException;
use App\Modules\Identity\Domain\Policy\RosterPolicy;
use App\Modules\Identity\Domain\Service\TemporaryPasswordGenerator;
use App\Modules\Identity\Domain\ValueObject\ClassroomId;
use App\Modules\Identity\Domain\ValueObject\ClassroomName;
use App\Modules\Identity\Domain\ValueObject\Email;
use App\Modules\Identity\Domain\ValueObject\HashedPassword;
use App\Modules\Identity\Domain\ValueObject\UserId;
use App\Modules\Identity\Domain\ValueObject\Username;
use App\Shared\Domain\Auth\Actor;
use App\Shared\Domain\Auth\Role;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Modules\Identity\Support\IdentityFakes;
use Tests\Unit\Modules\Identity\Support\InMemoryClassroomRepository;
use Tests\Unit\Modules\Identity\Support\InMemoryUserRepository;
use Tests\Unit\Modules\Identity\Support\RecordingTokenRevoker;

final class ResetStudentPasswordHandlerTest extends TestCase
{
    use IdentityFakes;

    private const CLASSROOM_ID = '0199c0de-1a2b-7c3d-8e4f-5a6b7c8d9e0f';

    private const SUBJECT_ID = '0190a2b4-0000-7000-8000-000000000001';

    public function test_the_assigned_teacher_resets_the_password_and_revokes_tokens(): void
    {
        $teacherId = UserId::random();
        $student = $this->student();
        $classrooms = $this->classroomsWith($teacherId, $student);
        $users = $this->users($student);
        $revoker = $this->revoker();

        $view = $this->handler($classrooms, $users, $revoker)
            ->handle(new ResetStudentPasswordCommand(new Actor($teacherId->value(), Role::Teacher), $student->id()->value()));

        self::assertNotNull($view->temporaryPassword);
        self::assertContains($student->id()->value(), $revoker->allCalls);
    }

    public function test_a_second_reset_returns_the_same_password_and_does_not_revoke_again(): void
    {
        $teacherId = UserId::random();
        $student = $this->student();
        $classrooms = $this->classroomsWith($teacherId, $student);
        $users = $this->users($student);
        $revoker = $this->revoker();
        $handler = $this->handler($classrooms, $users, $revoker);

        $first = $handler->handle(new ResetStudentPasswordCommand(new Actor($teacherId->value(), Role::Teacher), $student->id()->value()));
        $second = $handler->handle(new ResetStudentPasswordCommand(new Actor($teacherId->value(), Role::Teacher), $student->id()->value()));

        self::assertSame($first->temporaryPassword, $second->temporaryPassword);
        self::assertCount(1, $revoker->allCalls);
    }

    public function test_an_unrelated_teacher_is_denied(): void
    {
        $teacherId = UserId::random();
        $student = $this->student();
        $classrooms = $this->classroomsWith($teacherId, $student);
        $users = $this->users($student);

        $this->expectException(AccessDeniedException::class);

        $this->handler($classrooms, $users, $this->revoker())
            ->handle(new ResetStudentPasswordCommand(new Actor(UserId::random()->value(), Role::Teacher), $student->id()->value()));
    }

    public function test_a_staff_id_is_not_a_student(): void
    {
        $teacherId = UserId::random();
        $classrooms = $this->classroomsWith($teacherId, null);
        $staff = User::staff(UserId::random(), 'Beto', new Email('beto@escola.br'), $this->hash(), Role::Teacher, false);
        $users = $this->users($staff);

        $this->expectException(StudentNotFoundException::class);

        $this->handler($classrooms, $users, $this->revoker())
            ->handle(new ResetStudentPasswordCommand(new Actor($teacherId->value(), Role::Teacher), $staff->id()->value()));
    }

    private function handler(InMemoryClassroomRepository $classrooms, InMemoryUserRepository $users, RecordingTokenRevoker $revoker): ResetStudentPasswordHandler
    {
        $vault = $this->vault();

        return new ResetStudentPasswordHandler(
            $users,
            new RosterPolicy($classrooms),
            new PasswordIssuer($this->hasher(), $vault, new TemporaryPasswordGenerator),
            $revoker,
            $this->transactions(),
        );
    }

    private function classroomsWith(UserId $teacherId, ?User $student): InMemoryClassroomRepository
    {
        $classrooms = $this->classrooms();
        $classroom = Classroom::create(new ClassroomId(self::CLASSROOM_ID), new ClassroomName('Química 1'), self::SUBJECT_ID);
        $classroom->assignTeachers([User::staff($teacherId, 'Ana', new Email('ana@escola.br'), $this->hash(), Role::Teacher, false)]);
        if ($student !== null) {
            $classroom->enrol($student);
        }
        $classrooms->save($classroom);

        return $classrooms;
    }

    private function student(): User
    {
        return User::student(UserId::random(), 'Bia', new Username('bia'), $this->hash(), true);
    }

    private function hash(): HashedPassword
    {
        return new HashedPassword('$2y$04$x');
    }
}
