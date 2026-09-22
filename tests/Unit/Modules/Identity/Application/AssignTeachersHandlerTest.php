<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity\Application;

use App\Modules\Identity\Application\Command\AssignTeachers\AssignTeachersCommand;
use App\Modules\Identity\Application\Command\AssignTeachers\AssignTeachersHandler;
use App\Modules\Identity\Domain\Entity\Classroom;
use App\Modules\Identity\Domain\Entity\User;
use App\Modules\Identity\Domain\Exception\AccessDeniedException;
use App\Modules\Identity\Domain\Exception\ClassroomNotFoundException;
use App\Modules\Identity\Domain\Exception\TeacherNotFoundException;
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

final class AssignTeachersHandlerTest extends TestCase
{
    use IdentityFakes;

    private const CLASSROOM_ID = '0199c0de-1a2b-7c3d-8e4f-5a6b7c8d9e0f';

    private const SUBJECT_ID = '0190a2b4-0000-7000-8000-000000000001';

    public function test_an_admin_assigns_two_teachers(): void
    {
        $ana = $this->teacher('Ana');
        $root = $this->teacher('Root');
        $classrooms = $this->classroomsWith();
        $users = $this->users($ana, $root);

        $view = (new AssignTeachersHandler($classrooms, $users))
            ->handle(new AssignTeachersCommand($this->admin(), self::CLASSROOM_ID, [$ana->id()->value(), $root->id()->value()]));

        self::assertEqualsCanonicalizing([$ana->id()->value(), $root->id()->value()], $view->teacherIds);
    }

    public function test_a_student_id_is_not_a_valid_teacher_and_nothing_is_saved(): void
    {
        $classrooms = $this->classroomsWith();
        $student = User::student(UserId::random(), 'Bia', new Username('bia'), $this->hash(), false);
        $users = $this->users($student);

        $this->expectException(TeacherNotFoundException::class);

        (new AssignTeachersHandler($classrooms, $users))
            ->handle(new AssignTeachersCommand($this->admin(), self::CLASSROOM_ID, [$student->id()->value()]));

        self::assertSame([], $classrooms->findById(new ClassroomId(self::CLASSROOM_ID))?->teacherIds() ?? []);
    }

    public function test_an_unknown_teacher_id_is_not_found(): void
    {
        $classrooms = $this->classroomsWith();
        $users = $this->users();

        $this->expectException(TeacherNotFoundException::class);

        (new AssignTeachersHandler($classrooms, $users))
            ->handle(new AssignTeachersCommand($this->admin(), self::CLASSROOM_ID, [UserId::random()->value()]));
    }

    public function test_a_deactivated_teacher_id_is_not_found(): void
    {
        $teacher = $this->teacher('Ana');
        $teacher->deactivate(new DateTimeImmutable);
        $classrooms = $this->classroomsWith();
        $users = $this->users($teacher);

        $this->expectException(TeacherNotFoundException::class);

        (new AssignTeachersHandler($classrooms, $users))
            ->handle(new AssignTeachersCommand($this->admin(), self::CLASSROOM_ID, [$teacher->id()->value()]));
    }

    public function test_an_unknown_classroom_is_not_found(): void
    {
        $this->expectException(ClassroomNotFoundException::class);

        (new AssignTeachersHandler($this->classrooms(), $this->users()))
            ->handle(new AssignTeachersCommand($this->admin(), self::CLASSROOM_ID, []));
    }

    public function test_a_teacher_actor_cannot_assign_teachers(): void
    {
        $this->expectException(AccessDeniedException::class);

        (new AssignTeachersHandler($this->classroomsWith(), $this->users()))
            ->handle(new AssignTeachersCommand(new Actor('t', Role::Teacher), self::CLASSROOM_ID, []));
    }

    private function classroomsWith(): InMemoryClassroomRepository
    {
        $classrooms = $this->classrooms();
        $classrooms->save(Classroom::create(new ClassroomId(self::CLASSROOM_ID), new ClassroomName('Química 1'), self::SUBJECT_ID));

        return $classrooms;
    }

    private function teacher(string $name): User
    {
        return User::staff(UserId::random(), $name, new Email(mb_strtolower($name).'@escola.br'), $this->hash(), Role::Teacher, false);
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
