<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity\Application;

use App\Modules\Identity\Application\Command\SetStudentActive\SetStudentActiveCommand;
use App\Modules\Identity\Application\Command\SetStudentActive\SetStudentActiveHandler;
use App\Modules\Identity\Domain\Entity\Classroom;
use App\Modules\Identity\Domain\Entity\User;
use App\Modules\Identity\Domain\Exception\AccessDeniedException;
use App\Modules\Identity\Domain\Exception\StudentNotFoundException;
use App\Modules\Identity\Domain\Policy\RosterPolicy;
use App\Modules\Identity\Domain\ValueObject\ClassroomId;
use App\Modules\Identity\Domain\ValueObject\ClassroomName;
use App\Modules\Identity\Domain\ValueObject\Email;
use App\Modules\Identity\Domain\ValueObject\HashedPassword;
use App\Modules\Identity\Domain\ValueObject\UserId;
use App\Modules\Identity\Domain\ValueObject\Username;
use App\Shared\Domain\Auth\Actor;
use App\Shared\Domain\Auth\Role;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Modules\Identity\Support\IdentityFakes;
use Tests\Unit\Modules\Identity\Support\InMemoryClassroomRepository;

final class SetStudentActiveHandlerTest extends TestCase
{
    use IdentityFakes;

    private const CLASSROOM_ID = '0199c0de-1a2b-7c3d-8e4f-5a6b7c8d9e0f';

    private const SUBJECT_ID = '0190a2b4-0000-7000-8000-000000000001';

    public function test_the_assigned_teacher_deactivates_a_student_and_revokes_their_tokens(): void
    {
        $teacherId = UserId::random();
        $student = $this->student();
        $classrooms = $this->classroomsWith($teacherId, $student);
        $users = $this->users($student);
        $revoker = $this->revoker();

        $view = (new SetStudentActiveHandler($users, new RosterPolicy($classrooms), $revoker, $this->transactions()))
            ->handle(new SetStudentActiveCommand(new Actor($teacherId->value(), Role::Teacher), $student->id()->value(), false));

        self::assertFalse($view->active);
        self::assertNull($view->temporaryPassword);
        self::assertContains($student->id()->value(), $revoker->allCalls);
    }

    public function test_the_assigned_teacher_reactivates_a_student_without_revoking_tokens(): void
    {
        $teacherId = UserId::random();
        $student = $this->student();
        // Enrol while active: a classroom's roster is independent of the account's
        // lifecycle, so deactivating afterwards does not drop the enrolment.
        $classrooms = $this->classroomsWith($teacherId, $student);
        $student->deactivate(new DateTimeImmutable);
        $users = $this->users($student);
        $revoker = $this->revoker();

        $view = (new SetStudentActiveHandler($users, new RosterPolicy($classrooms), $revoker, $this->transactions()))
            ->handle(new SetStudentActiveCommand(new Actor($teacherId->value(), Role::Teacher), $student->id()->value(), true));

        self::assertTrue($view->active);
        self::assertSame([], $revoker->allCalls);
    }

    public function test_an_unrelated_teacher_is_denied(): void
    {
        $teacherId = UserId::random();
        $student = $this->student();
        $classrooms = $this->classroomsWith($teacherId, $student);
        $users = $this->users($student);

        $this->expectException(AccessDeniedException::class);

        (new SetStudentActiveHandler($users, new RosterPolicy($classrooms), $this->revoker(), $this->transactions()))
            ->handle(new SetStudentActiveCommand(new Actor(UserId::random()->value(), Role::Teacher), $student->id()->value(), false));
    }

    public function test_a_staff_id_is_not_a_student(): void
    {
        $teacherId = UserId::random();
        $classrooms = $this->classroomsWith($teacherId, null);
        $staff = User::staff(UserId::random(), 'Beto', new Email('beto@escola.br'), $this->hash(), Role::Teacher, false);
        $users = $this->users($staff);

        $this->expectException(StudentNotFoundException::class);

        (new SetStudentActiveHandler($users, new RosterPolicy($classrooms), $this->revoker(), $this->transactions()))
            ->handle(new SetStudentActiveCommand(new Actor($teacherId->value(), Role::Teacher), $staff->id()->value(), false));
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
