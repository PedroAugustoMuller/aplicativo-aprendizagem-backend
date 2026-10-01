<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity\Application;

use App\Modules\Identity\Application\Command\CreateClassroom\CreateClassroomCommand;
use App\Modules\Identity\Application\Command\CreateClassroom\CreateClassroomHandler;
use App\Modules\Identity\Domain\Entity\Classroom;
use App\Modules\Identity\Domain\Exception\AccessDeniedException;
use App\Modules\Identity\Domain\Exception\ClassroomNameAlreadyTakenException;
use App\Modules\Identity\Domain\Exception\ClassroomSubjectInactiveException;
use App\Modules\Identity\Domain\ValueObject\ClassroomId;
use App\Modules\Identity\Domain\ValueObject\ClassroomName;
use App\Shared\Domain\Auth\Actor;
use App\Shared\Domain\Auth\Role;
use App\Shared\Domain\Exception\IdempotencyConflictException;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Modules\Identity\Support\IdentityFakes;

final class CreateClassroomHandlerTest extends TestCase
{
    use IdentityFakes;

    private const ID = '0199c0de-1a2b-7c3d-8e4f-5a6b7c8d9e0f';

    private const OTHER_ID = '0199c0de-1a2b-7c3d-8e4f-5a6b7c8d9e10';

    private const SUBJECT_ID = '0190a2b4-0000-7000-8000-000000000001';

    public function test_an_admin_creates_a_classroom_for_an_active_subject(): void
    {
        $classrooms = $this->classrooms();

        $view = (new CreateClassroomHandler($classrooms, $this->subjectCatalog([self::SUBJECT_ID])))
            ->handle(new CreateClassroomCommand($this->admin(), self::ID, 'Química 1', self::SUBJECT_ID));

        self::assertSame(self::ID, $view->id);
        self::assertSame('Química 1', $view->name);
        self::assertSame(self::SUBJECT_ID, $view->subjectId);
        self::assertSame([], $view->teacherIds);
        self::assertSame(0, $view->studentCount);
        self::assertTrue($view->active);
    }

    public function test_a_teacher_cannot_create_a_classroom(): void
    {
        $this->expectException(AccessDeniedException::class);

        (new CreateClassroomHandler($this->classrooms(), $this->subjectCatalog([self::SUBJECT_ID])))
            ->handle(new CreateClassroomCommand(new Actor('t', Role::Teacher), self::ID, 'Química 1', self::SUBJECT_ID));
    }

    public function test_an_inactive_or_unknown_subject_is_rejected(): void
    {
        $this->expectException(ClassroomSubjectInactiveException::class);

        (new CreateClassroomHandler($this->classrooms(), $this->subjectCatalog([])))
            ->handle(new CreateClassroomCommand($this->admin(), self::ID, 'Química 1', self::SUBJECT_ID));
    }

    public function test_a_name_taken_by_another_classroom_is_rejected_case_insensitively(): void
    {
        $classrooms = $this->classrooms();
        $classrooms->save(Classroom::create(new ClassroomId(self::ID), new ClassroomName('Química 1'), self::SUBJECT_ID));

        $this->expectException(ClassroomNameAlreadyTakenException::class);

        (new CreateClassroomHandler($classrooms, $this->subjectCatalog([self::SUBJECT_ID])))
            ->handle(new CreateClassroomCommand($this->admin(), self::OTHER_ID, 'química 1', self::SUBJECT_ID));
    }

    public function test_replaying_the_same_id_and_payload_returns_the_original(): void
    {
        $classrooms = $this->classrooms();
        $handler = new CreateClassroomHandler($classrooms, $this->subjectCatalog([self::SUBJECT_ID]));
        $handler->handle(new CreateClassroomCommand($this->admin(), self::ID, 'Química 1', self::SUBJECT_ID));

        $again = $handler->handle(new CreateClassroomCommand($this->admin(), self::ID, 'Química 1', self::SUBJECT_ID));

        self::assertSame('Química 1', $again->name);
    }

    public function test_replaying_the_same_id_with_a_different_name_conflicts(): void
    {
        $classrooms = $this->classrooms();
        $handler = new CreateClassroomHandler($classrooms, $this->subjectCatalog([self::SUBJECT_ID]));
        $handler->handle(new CreateClassroomCommand($this->admin(), self::ID, 'Química 1', self::SUBJECT_ID));

        $this->expectException(IdempotencyConflictException::class);
        $handler->handle(new CreateClassroomCommand($this->admin(), self::ID, 'Química 2', self::SUBJECT_ID));
    }

    public function test_replaying_the_same_id_with_a_different_subject_conflicts(): void
    {
        $otherSubject = '0190a2b4-0000-7000-8000-000000000002';
        $classrooms = $this->classrooms();
        $handler = new CreateClassroomHandler($classrooms, $this->subjectCatalog([self::SUBJECT_ID, $otherSubject]));
        $handler->handle(new CreateClassroomCommand($this->admin(), self::ID, 'Química 1', self::SUBJECT_ID));

        $this->expectException(IdempotencyConflictException::class);
        $handler->handle(new CreateClassroomCommand($this->admin(), self::ID, 'Química 1', $otherSubject));
    }

    private function admin(): Actor
    {
        return new Actor('a', Role::Admin);
    }
}
