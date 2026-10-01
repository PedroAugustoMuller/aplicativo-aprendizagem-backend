<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity\Domain;

use App\Modules\Identity\Domain\Entity\Classroom;
use App\Modules\Identity\Domain\Entity\User;
use App\Modules\Identity\Domain\Exception\EnrolmentRequiresActiveStudentException;
use App\Modules\Identity\Domain\ValueObject\ClassroomId;
use App\Modules\Identity\Domain\ValueObject\ClassroomName;
use App\Modules\Identity\Domain\ValueObject\Email;
use App\Modules\Identity\Domain\ValueObject\HashedPassword;
use App\Modules\Identity\Domain\ValueObject\UserId;
use App\Modules\Identity\Domain\ValueObject\Username;
use App\Shared\Domain\Auth\Role;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ClassroomTest extends TestCase
{
    public function test_enrolling_is_idempotent_and_rejects_non_students(): void
    {
        $classroom = $this->classroom();
        $bia = User::student(UserId::random(), 'Bia', new Username('bia'), $this->hash(), true);

        $classroom->enrol($bia);
        $classroom->enrol($bia);
        self::assertCount(1, $classroom->studentIds());
        self::assertTrue($classroom->hasStudent($bia->id()));

        $this->expectException(EnrolmentRequiresActiveStudentException::class);
        $classroom->enrol(User::staff(UserId::random(), 'Ana', new Email('ana@escola.br'), $this->hash(), Role::Teacher, false));
    }

    public function test_a_deactivated_student_cannot_be_enrolled(): void
    {
        $bia = User::student(UserId::random(), 'Bia', new Username('bia'), $this->hash(), true);
        $bia->deactivate(new DateTimeImmutable);

        $this->expectException(EnrolmentRequiresActiveStudentException::class);
        $this->classroom()->enrol($bia);
    }

    public function test_assigning_teachers_replaces_the_set_and_admins_count_as_teachers(): void
    {
        $classroom = $this->classroom();
        $ana = User::staff(UserId::random(), 'Ana', new Email('ana@escola.br'), $this->hash(), Role::Teacher, false);
        $root = User::staff(UserId::random(), 'Root', new Email('root@escola.br'), $this->hash(), Role::Admin, false);

        $classroom->assignTeachers([$ana, $root]);
        $classroom->assignTeachers([$ana]);

        self::assertTrue($classroom->isTaughtBy($ana->id()));
        self::assertFalse($classroom->isTaughtBy($root->id()));
    }

    public function test_unenrol_removes_only_that_student(): void
    {
        $classroom = $this->classroom();
        $a = User::student(UserId::random(), 'A', new Username('a'), $this->hash(), true);
        $b = User::student(UserId::random(), 'B', new Username('b'), $this->hash(), true);
        $classroom->enrol($a);
        $classroom->enrol($b);

        $classroom->unenrol($a->id());

        self::assertFalse($classroom->hasStudent($a->id()));
        self::assertTrue($classroom->hasStudent($b->id()));
    }

    private function classroom(): Classroom
    {
        return Classroom::create(ClassroomId::random(), new ClassroomName('Química 1'), '0190a2b4-0000-7000-8000-000000000001');
    }

    public function test_a_restored_classroom_reports_no_changes(): void
    {
        $classroom = Classroom::restore(ClassroomId::random(), new ClassroomName('Química 1'), '0190a2b4-0000-7000-8000-000000000001', [], [], null);

        self::assertFalse($classroom->isNew());
        self::assertFalse($classroom->detailsChanged());
        self::assertFalse($classroom->teachersChanged());
        self::assertSame([], $classroom->enrolledStudentIds());
        self::assertSame([], $classroom->unenrolledStudentIds());
    }

    public function test_it_tracks_enrolments_and_unenrolments_since_it_was_loaded(): void
    {
        $bia = User::student(UserId::random(), 'Bia', new Username('bia'), $this->hash(), true);
        $caio = User::student(UserId::random(), 'Caio', new Username('caio'), $this->hash(), true);
        $classroom = Classroom::restore(ClassroomId::random(), new ClassroomName('Química 1'), '0190a2b4-0000-7000-8000-000000000001', [], [$caio->id()], null);

        $classroom->enrol($bia);
        $classroom->unenrol($caio->id());

        self::assertEquals([$bia->id()], $classroom->enrolledStudentIds());
        self::assertEquals([$caio->id()], $classroom->unenrolledStudentIds());
    }

    public function test_enrolling_then_unenrolling_the_same_student_leaves_only_the_unenrolment(): void
    {
        $bia = User::student(UserId::random(), 'Bia', new Username('bia'), $this->hash(), true);
        $classroom = Classroom::create(ClassroomId::random(), new ClassroomName('Química 1'), '0190a2b4-0000-7000-8000-000000000001');

        $classroom->enrol($bia);
        $classroom->unenrol($bia->id());

        self::assertTrue($classroom->isNew());
        self::assertSame([], $classroom->enrolledStudentIds());
        self::assertEquals([$bia->id()], $classroom->unenrolledStudentIds());
    }

    public function test_deactivating_twice_changes_details_once(): void
    {
        $classroom = Classroom::restore(ClassroomId::random(), new ClassroomName('Química 1'), '0190a2b4-0000-7000-8000-000000000001', [], [], new DateTimeImmutable('2026-01-01'));

        $classroom->deactivate(new DateTimeImmutable('2026-02-01'));

        self::assertFalse($classroom->detailsChanged());
    }

    private function hash(): HashedPassword
    {
        return new HashedPassword('$2y$04$x');
    }
}
