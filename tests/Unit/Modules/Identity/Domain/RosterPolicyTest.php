<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity\Domain;

use App\Modules\Identity\Domain\Entity\Classroom;
use App\Modules\Identity\Domain\Entity\User;
use App\Modules\Identity\Domain\Policy\RosterPolicy;
use App\Modules\Identity\Domain\ValueObject\ClassroomId;
use App\Modules\Identity\Domain\ValueObject\ClassroomName;
use App\Modules\Identity\Domain\ValueObject\Email;
use App\Modules\Identity\Domain\ValueObject\HashedPassword;
use App\Modules\Identity\Domain\ValueObject\UserId;
use App\Modules\Identity\Domain\ValueObject\Username;
use App\Shared\Domain\Auth\Actor;
use App\Shared\Domain\Auth\Role;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Modules\Identity\Support\InMemoryClassroomRepository;

final class RosterPolicyTest extends TestCase
{
    private const SUBJECT_ID = '0190a2b4-0000-7000-8000-000000000001';

    public function test_an_admin_manages_any_classroom(): void
    {
        $policy = new RosterPolicy(new InMemoryClassroomRepository);

        self::assertTrue($policy->canManageClassroom(new Actor('a', Role::Admin), $this->classroom()));
    }

    public function test_a_teacher_manages_a_classroom_they_teach_and_not_another(): void
    {
        $teacherId = UserId::random();
        $classroom = $this->classroom();
        $classroom->assignTeachers([$this->teacher($teacherId)]);
        $otherClassroom = $this->classroom();

        $policy = new RosterPolicy(new InMemoryClassroomRepository);

        self::assertTrue($policy->canManageClassroom(new Actor($teacherId->value(), Role::Teacher), $classroom));
        self::assertFalse($policy->canManageClassroom(new Actor($teacherId->value(), Role::Teacher), $otherClassroom));
    }

    public function test_a_student_manages_no_classroom(): void
    {
        $policy = new RosterPolicy(new InMemoryClassroomRepository);

        self::assertFalse($policy->canManageClassroom(new Actor('s', Role::Student), $this->classroom()));
    }

    public function test_a_teacher_manages_a_student_enrolled_in_one_of_several_classrooms_they_teach(): void
    {
        $teacherId = UserId::random();
        $studentId = UserId::random();

        $taught = $this->classroom();
        $taught->assignTeachers([$this->teacher($teacherId)]);
        $taught->enrol($this->student($studentId));

        $unrelated = $this->classroom();
        $unrelated->enrol($this->student($studentId));

        $classrooms = new InMemoryClassroomRepository;
        $classrooms->save($taught);
        $classrooms->save($unrelated);

        $policy = new RosterPolicy($classrooms);

        self::assertTrue($policy->canManageStudent(new Actor($teacherId->value(), Role::Teacher), $studentId));
    }

    public function test_a_teacher_of_an_unrelated_classroom_cannot_manage_the_student(): void
    {
        $studentId = UserId::random();

        $unrelated = $this->classroom();
        $unrelated->enrol($this->student($studentId));

        $classrooms = new InMemoryClassroomRepository;
        $classrooms->save($unrelated);

        $policy = new RosterPolicy($classrooms);

        self::assertFalse($policy->canManageStudent(new Actor(UserId::random()->value(), Role::Teacher), $studentId));
    }

    public function test_an_admin_manages_any_student(): void
    {
        $policy = new RosterPolicy(new InMemoryClassroomRepository);

        self::assertTrue($policy->canManageStudent(new Actor('a', Role::Admin), UserId::random()));
    }

    public function test_a_student_manages_no_student(): void
    {
        $policy = new RosterPolicy(new InMemoryClassroomRepository);

        self::assertFalse($policy->canManageStudent(new Actor('s', Role::Student), UserId::random()));
    }

    private function classroom(): Classroom
    {
        return Classroom::create(ClassroomId::random(), new ClassroomName('Química 1'), self::SUBJECT_ID);
    }

    private function teacher(UserId $id): User
    {
        return User::staff($id, 'Ana', new Email('ana@escola.br'), $this->hash(), Role::Teacher, false);
    }

    private function student(UserId $id): User
    {
        return User::student($id, 'Bia', new Username('bia'.substr($id->value(), 0, 8)), $this->hash(), true);
    }

    private function hash(): HashedPassword
    {
        return new HashedPassword('$2y$04$x');
    }
}
