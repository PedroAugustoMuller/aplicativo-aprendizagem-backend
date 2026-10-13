<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity\Application;

use App\Modules\Identity\Application\Command\ReplaceClassroomSubjects\ReplaceClassroomSubjectsCommand;
use App\Modules\Identity\Application\Command\ReplaceClassroomSubjects\ReplaceClassroomSubjectsHandler;
use App\Modules\Identity\Domain\Entity\Classroom;
use App\Modules\Identity\Domain\Entity\User;
use App\Modules\Identity\Domain\Exception\AccessDeniedException;
use App\Modules\Identity\Domain\Exception\ClassroomNotFoundException;
use App\Modules\Identity\Domain\Exception\ClassroomSubjectInactiveException;
use App\Modules\Identity\Domain\ValueObject\ClassroomId;
use App\Modules\Identity\Domain\ValueObject\ClassroomName;
use App\Modules\Identity\Domain\ValueObject\Email;
use App\Modules\Identity\Domain\ValueObject\HashedPassword;
use App\Modules\Identity\Domain\ValueObject\UserId;
use App\Shared\Domain\Auth\Actor;
use App\Shared\Domain\Auth\Role;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Modules\Identity\Support\IdentityFakes;
use Tests\Unit\Modules\Identity\Support\InMemoryClassroomRepository;

final class ReplaceClassroomSubjectsHandlerTest extends TestCase
{
    use IdentityFakes;

    private const CLASSROOM_ID = '0199c0de-1a2b-7c3d-8e4f-5a6b7c8d9e0f';

    public function test_replaces_the_subjects_and_drops_assignments_of_removed_ones(): void
    {
        $classrooms = $this->classroomsWith(['mat', 'port']);
        $classroom = $classrooms->findById(new ClassroomId(self::CLASSROOM_ID));
        self::assertNotNull($classroom);
        $classroom->replaceAssignments([[User::staff(UserId::random(), 'Carlos', new Email('carlos@escola.br'), new HashedPassword('$2y$04$x'), Role::Teacher, false), 'mat']]);

        $view = (new ReplaceClassroomSubjectsHandler($classrooms, $this->subjectCatalog(['port', 'hist'])))
            ->handle(new ReplaceClassroomSubjectsCommand($this->admin(), self::CLASSROOM_ID, ['port', 'hist']));

        self::assertSame(['port', 'hist'], $view->subjectIds);
        self::assertSame([], $view->teachers);
    }

    public function test_keeps_an_already_assigned_subject_even_if_it_was_deactivated(): void
    {
        $view = (new ReplaceClassroomSubjectsHandler($this->classroomsWith(['old']), $this->subjectCatalog(['mat'])))
            ->handle(new ReplaceClassroomSubjectsCommand($this->admin(), self::CLASSROOM_ID, ['old', 'mat']));

        self::assertSame(['old', 'mat'], $view->subjectIds);
    }

    public function test_adding_an_inactive_subject_is_rejected(): void
    {
        $this->expectException(ClassroomSubjectInactiveException::class);

        (new ReplaceClassroomSubjectsHandler($this->classroomsWith([]), $this->subjectCatalog([])))
            ->handle(new ReplaceClassroomSubjectsCommand($this->admin(), self::CLASSROOM_ID, ['gone']));
    }

    public function test_an_unknown_classroom_is_not_found(): void
    {
        $this->expectException(ClassroomNotFoundException::class);

        (new ReplaceClassroomSubjectsHandler($this->classrooms(), $this->subjectCatalog([])))
            ->handle(new ReplaceClassroomSubjectsCommand($this->admin(), self::CLASSROOM_ID, []));
    }

    public function test_only_an_admin_may_replace_subjects(): void
    {
        $this->expectException(AccessDeniedException::class);

        (new ReplaceClassroomSubjectsHandler($this->classroomsWith([]), $this->subjectCatalog([])))
            ->handle(new ReplaceClassroomSubjectsCommand(new Actor('t', Role::Teacher), self::CLASSROOM_ID, []));
    }

    /** @param  list<string>  $subjectIds */
    private function classroomsWith(array $subjectIds): InMemoryClassroomRepository
    {
        $classrooms = $this->classrooms();
        $classrooms->save(Classroom::create(new ClassroomId(self::CLASSROOM_ID), new ClassroomName('7º A'), $subjectIds));

        return $classrooms;
    }

    private function admin(): Actor
    {
        return new Actor('a', Role::Admin);
    }
}
