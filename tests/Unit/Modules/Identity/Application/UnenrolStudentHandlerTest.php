<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity\Application;

use App\Modules\Identity\Application\Command\UnenrolStudent\UnenrolStudentCommand;
use App\Modules\Identity\Application\Command\UnenrolStudent\UnenrolStudentHandler;
use App\Modules\Identity\Domain\Entity\Classroom;
use App\Modules\Identity\Domain\Entity\User;
use App\Modules\Identity\Domain\Exception\AccessDeniedException;
use App\Modules\Identity\Domain\Exception\ClassroomNotFoundException;
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

final class UnenrolStudentHandlerTest extends TestCase
{
    use IdentityFakes;

    private const CLASSROOM_ID = '0199c0de-1a2b-7c3d-8e4f-5a6b7c8d9e0f';

    private const SUBJECT_ID = '0190a2b4-0000-7000-8000-000000000001';

    public function test_the_assigned_teacher_unenrols_a_student(): void
    {
        $teacherId = UserId::random();
        $student = $this->student();
        $classrooms = $this->classroomsWith($teacherId, $student);

        $view = (new UnenrolStudentHandler($classrooms, new RosterPolicy($classrooms)))
            ->handle(new UnenrolStudentCommand(new Actor($teacherId->value(), Role::Teacher), self::CLASSROOM_ID, $student->id()->value()));

        self::assertSame(0, $view->studentCount);
    }

    public function test_unenrolling_a_student_not_in_the_classroom_is_a_no_op(): void
    {
        $teacherId = UserId::random();
        $classrooms = $this->classroomsWith($teacherId, null);

        $view = (new UnenrolStudentHandler($classrooms, new RosterPolicy($classrooms)))
            ->handle(new UnenrolStudentCommand(new Actor($teacherId->value(), Role::Teacher), self::CLASSROOM_ID, UserId::random()->value()));

        self::assertSame(0, $view->studentCount);
    }

    public function test_an_unrelated_teacher_is_denied(): void
    {
        $teacherId = UserId::random();
        $student = $this->student();
        $classrooms = $this->classroomsWith($teacherId, $student);

        $this->expectException(AccessDeniedException::class);

        (new UnenrolStudentHandler($classrooms, new RosterPolicy($classrooms)))
            ->handle(new UnenrolStudentCommand(new Actor(UserId::random()->value(), Role::Teacher), self::CLASSROOM_ID, $student->id()->value()));
    }

    public function test_a_deactivated_classroom_is_not_found(): void
    {
        $teacherId = UserId::random();
        $student = $this->student();
        $classrooms = $this->classroomsWith($teacherId, $student);
        $classroom = $classrooms->findById(new ClassroomId(self::CLASSROOM_ID));
        self::assertNotNull($classroom);
        $classroom->deactivate(new DateTimeImmutable);
        $classrooms->save($classroom);

        $this->expectException(ClassroomNotFoundException::class);

        (new UnenrolStudentHandler($classrooms, new RosterPolicy($classrooms)))
            ->handle(new UnenrolStudentCommand(new Actor($teacherId->value(), Role::Teacher), self::CLASSROOM_ID, $student->id()->value()));
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
