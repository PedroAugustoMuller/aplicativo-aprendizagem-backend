<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity\Application;

use App\Modules\Identity\Application\Command\EnrolStudent\EnrolStudentCommand;
use App\Modules\Identity\Application\Command\EnrolStudent\EnrolStudentHandler;
use App\Modules\Identity\Domain\Entity\Classroom;
use App\Modules\Identity\Domain\Entity\User;
use App\Modules\Identity\Domain\Exception\AccessDeniedException;
use App\Modules\Identity\Domain\Exception\ClassroomNotFoundException;
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

final class EnrolStudentHandlerTest extends TestCase
{
    use IdentityFakes;

    private const CLASSROOM_ID = '0199c0de-1a2b-7c3d-8e4f-5a6b7c8d9e0f';

    private const SUBJECT_ID = '0190a2b4-0000-7000-8000-000000000001';

    public function test_the_assigned_teacher_enrols_a_student_who_is_in_no_other_classroom(): void
    {
        $teacherId = UserId::random();
        $classrooms = $this->classroomsWith($teacherId);
        $student = $this->student();
        $users = $this->users($student);

        $view = (new EnrolStudentHandler($classrooms, $users, new RosterPolicy($classrooms)))
            ->handle(new EnrolStudentCommand(new Actor($teacherId->value(), Role::Teacher), self::CLASSROOM_ID, $student->id()->value()));

        self::assertSame(1, $view->studentCount);
    }

    public function test_an_unrelated_teacher_is_denied(): void
    {
        $classrooms = $this->classroomsWith(UserId::random());
        $student = $this->student();
        $users = $this->users($student);

        $this->expectException(AccessDeniedException::class);

        (new EnrolStudentHandler($classrooms, $users, new RosterPolicy($classrooms)))
            ->handle(new EnrolStudentCommand(new Actor(UserId::random()->value(), Role::Teacher), self::CLASSROOM_ID, $student->id()->value()));
    }

    public function test_a_deactivated_classroom_is_not_found(): void
    {
        $teacherId = UserId::random();
        $classrooms = $this->classroomsWith($teacherId);
        $classroom = $classrooms->findById(new ClassroomId(self::CLASSROOM_ID));
        self::assertNotNull($classroom);
        $classroom->deactivate(new DateTimeImmutable);
        $classrooms->save($classroom);
        $student = $this->student();
        $users = $this->users($student);

        $this->expectException(ClassroomNotFoundException::class);

        (new EnrolStudentHandler($classrooms, $users, new RosterPolicy($classrooms)))
            ->handle(new EnrolStudentCommand(new Actor($teacherId->value(), Role::Teacher), self::CLASSROOM_ID, $student->id()->value()));
    }

    public function test_a_staff_id_is_not_a_student(): void
    {
        $teacherId = UserId::random();
        $classrooms = $this->classroomsWith($teacherId);
        $staff = User::staff(UserId::random(), 'Beto', new Email('beto@escola.br'), $this->hash(), Role::Teacher, false);
        $users = $this->users($staff);

        $this->expectException(StudentNotFoundException::class);

        (new EnrolStudentHandler($classrooms, $users, new RosterPolicy($classrooms)))
            ->handle(new EnrolStudentCommand(new Actor($teacherId->value(), Role::Teacher), self::CLASSROOM_ID, $staff->id()->value()));
    }

    private function classroomsWith(UserId $teacherId): InMemoryClassroomRepository
    {
        $classrooms = $this->classrooms();
        $classroom = Classroom::create(new ClassroomId(self::CLASSROOM_ID), new ClassroomName('Química 1'), self::SUBJECT_ID);
        $classroom->assignTeachers([User::staff($teacherId, 'Ana', new Email('ana@escola.br'), $this->hash(), Role::Teacher, false)]);
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
