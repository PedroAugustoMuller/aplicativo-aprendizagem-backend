<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity\Application;

use App\Modules\Identity\Application\Command\CreateStudents\CreateStudentsCommand;
use App\Modules\Identity\Application\Command\CreateStudents\CreateStudentsHandler;
use App\Modules\Identity\Application\Service\PasswordIssuer;
use App\Modules\Identity\Domain\Entity\Classroom;
use App\Modules\Identity\Domain\Entity\User;
use App\Modules\Identity\Domain\Exception\AccessDeniedException;
use App\Modules\Identity\Domain\Exception\ClassroomNotFoundException;
use App\Modules\Identity\Domain\Policy\RosterPolicy;
use App\Modules\Identity\Domain\Service\TemporaryPasswordGenerator;
use App\Modules\Identity\Domain\Service\UsernameGenerator;
use App\Modules\Identity\Domain\ValueObject\ClassroomId;
use App\Modules\Identity\Domain\ValueObject\ClassroomName;
use App\Modules\Identity\Domain\ValueObject\Email;
use App\Modules\Identity\Domain\ValueObject\HashedPassword;
use App\Modules\Identity\Domain\ValueObject\UserId;
use App\Modules\Identity\Domain\ValueObject\Username;
use App\Shared\Domain\Auth\Actor;
use App\Shared\Domain\Auth\Role;
use App\Shared\Domain\Exception\IdempotencyConflictException;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Modules\Identity\Support\IdentityFakes;
use Tests\Unit\Modules\Identity\Support\InMemoryClassroomRepository;
use Tests\Unit\Modules\Identity\Support\InMemoryCredentialVault;
use Tests\Unit\Modules\Identity\Support\InMemoryUserRepository;

final class CreateStudentsHandlerTest extends TestCase
{
    use IdentityFakes;

    private const CLASSROOM_ID = '0199c0de-1a2b-7c3d-8e4f-5a6b7c8d9e0f';

    private const SUBJECT_ID = '0190a2b4-0000-7000-8000-000000000001';

    public function test_the_assigned_teacher_creates_two_students_with_the_same_name(): void
    {
        $teacherId = UserId::random();
        $classrooms = $this->classroomsWith($teacherId);
        $users = $this->users();
        $vault = $this->vault();
        $id1 = UserId::random()->value();
        $id2 = UserId::random()->value();

        $views = $this->handler($classrooms, $users, $vault)->handle(new CreateStudentsCommand(
            $this->actorFor($teacherId),
            self::CLASSROOM_ID,
            [['id' => $id1, 'name' => 'Ana Souza'], ['id' => $id2, 'name' => 'Ana Souza']],
        ));

        self::assertSame('ana.souza', $views[0]->login);
        self::assertSame('ana.souza2', $views[1]->login);
        self::assertTrue($views[0]->mustChangePassword);
        self::assertTrue($views[1]->mustChangePassword);
        self::assertNotNull($views[0]->temporaryPassword);
        self::assertNotNull($views[1]->temporaryPassword);
        self::assertSame($views[0]->temporaryPassword, $vault->reveal(new UserId($id1)));
        self::assertSame($views[1]->temporaryPassword, $vault->reveal(new UserId($id2)));

        $classroom = $classrooms->findById(new ClassroomId(self::CLASSROOM_ID));
        self::assertNotNull($classroom);
        self::assertTrue($classroom->hasStudent(new UserId($id1)));
        self::assertTrue($classroom->hasStudent(new UserId($id2)));
    }

    public function test_an_unrelated_teacher_is_denied_and_nothing_is_saved(): void
    {
        $classrooms = $this->classroomsWith(UserId::random());
        $users = $this->users();

        try {
            $this->handler($classrooms, $users, $this->vault())->handle(new CreateStudentsCommand(
                $this->actorFor(UserId::random()),
                self::CLASSROOM_ID,
                [['id' => UserId::random()->value(), 'name' => 'Ana Souza']],
            ));
            self::fail('Expected AccessDeniedException.');
        } catch (AccessDeniedException) {
            self::assertSame([], $classrooms->findById(new ClassroomId(self::CLASSROOM_ID))?->studentIds() ?? []);
        }
    }

    public function test_a_deactivated_classroom_is_not_found(): void
    {
        $teacherId = UserId::random();
        $classrooms = $this->classroomsWith($teacherId);
        $classroom = $classrooms->findById(new ClassroomId(self::CLASSROOM_ID));
        self::assertNotNull($classroom);
        $classroom->deactivate(new DateTimeImmutable);
        $classrooms->save($classroom);

        $this->expectException(ClassroomNotFoundException::class);

        $this->handler($classrooms, $this->users(), $this->vault())->handle(new CreateStudentsCommand(
            $this->actorFor($teacherId),
            self::CLASSROOM_ID,
            [['id' => UserId::random()->value(), 'name' => 'Ana Souza']],
        ));
    }

    public function test_replaying_the_whole_batch_returns_the_same_usernames_and_passwords_without_new_users(): void
    {
        $teacherId = UserId::random();
        $classrooms = $this->classroomsWith($teacherId);
        $users = $this->users();
        $vault = $this->vault();
        $handler = $this->handler($classrooms, $users, $vault);
        $id1 = UserId::random()->value();
        $id2 = UserId::random()->value();
        $students = [['id' => $id1, 'name' => 'Ana Souza'], ['id' => $id2, 'name' => 'Ana Souza']];

        $first = $handler->handle(new CreateStudentsCommand($this->actorFor($teacherId), self::CLASSROOM_ID, $students));
        $countAfterFirst = count($users->byId);

        $second = $handler->handle(new CreateStudentsCommand($this->actorFor($teacherId), self::CLASSROOM_ID, $students));

        self::assertSame($countAfterFirst, count($users->byId));
        self::assertSame($first[0]->login, $second[0]->login);
        self::assertSame($first[1]->login, $second[1]->login);
        self::assertSame($first[0]->temporaryPassword, $second[0]->temporaryPassword);
        self::assertSame($first[1]->temporaryPassword, $second[1]->temporaryPassword);
    }

    public function test_a_row_id_that_exists_with_a_different_name_conflicts_and_persists_nothing(): void
    {
        $teacherId = UserId::random();
        $classrooms = $this->classroomsWith($teacherId);
        $existing = User::student(UserId::random(), 'Ana Souza', new Username('ana.souza'), $this->hash(), true);
        $users = $this->users($existing);
        $newId = UserId::random()->value();

        try {
            $this->handler($classrooms, $users, $this->vault())->handle(new CreateStudentsCommand(
                $this->actorFor($teacherId),
                self::CLASSROOM_ID,
                [['id' => $existing->id()->value(), 'name' => 'Outro Nome'], ['id' => $newId, 'name' => 'Beto Lima']],
            ));
            self::fail('Expected IdempotencyConflictException.');
        } catch (IdempotencyConflictException) {
            self::assertNull($users->findById(new UserId($newId)));
            self::assertSame([], $classrooms->findById(new ClassroomId(self::CLASSROOM_ID))?->studentIds() ?? []);
        }
    }

    public function test_a_row_id_that_already_exists_as_a_staff_user_conflicts(): void
    {
        $teacherId = UserId::random();
        $classrooms = $this->classroomsWith($teacherId);
        $staff = User::staff(UserId::random(), 'Ana Souza', new Email('ana@escola.br'), $this->hash(), Role::Teacher, false);
        $users = $this->users($staff);

        $this->expectException(IdempotencyConflictException::class);

        $this->handler($classrooms, $users, $this->vault())->handle(new CreateStudentsCommand(
            $this->actorFor($teacherId),
            self::CLASSROOM_ID,
            [['id' => $staff->id()->value(), 'name' => 'Ana Souza']],
        ));
    }

    private function handler(InMemoryClassroomRepository $classrooms, InMemoryUserRepository $users, InMemoryCredentialVault $vault): CreateStudentsHandler
    {
        return new CreateStudentsHandler(
            $classrooms,
            $users,
            new RosterPolicy($classrooms),
            new UsernameGenerator,
            new PasswordIssuer($this->hasher(), $vault, new TemporaryPasswordGenerator),
            $vault,
            $this->transactions(),
        );
    }

    private function classroomsWith(UserId $teacherId): InMemoryClassroomRepository
    {
        $classrooms = $this->classrooms();
        $classroom = Classroom::create(new ClassroomId(self::CLASSROOM_ID), new ClassroomName('Química 1'), self::SUBJECT_ID);
        $classroom->assignTeachers([User::staff($teacherId, 'Ana', new Email('ana@escola.br'), $this->hash(), Role::Teacher, false)]);
        $classrooms->save($classroom);

        return $classrooms;
    }

    private function actorFor(UserId $id): Actor
    {
        return new Actor($id->value(), Role::Teacher);
    }

    private function hash(): HashedPassword
    {
        return new HashedPassword('$2y$04$x');
    }
}
