<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity\Application;

use App\Modules\Identity\Application\Query\ListStudents\ListStudentsHandler;
use App\Modules\Identity\Application\Query\ListStudents\ListStudentsQuery;
use App\Modules\Identity\Application\Query\ListTeachers\AccountListItem;
use App\Modules\Identity\Application\Query\ListTeachers\AccountListReader;
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
use App\Shared\Domain\Auth\Actor;
use App\Shared\Domain\Auth\Role;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Modules\Identity\Support\IdentityFakes;
use Tests\Unit\Modules\Identity\Support\InMemoryClassroomRepository;

final class ListStudentsHandlerTest extends TestCase
{
    use IdentityFakes;

    private const CLASSROOM_ID = '0199c0de-1a2b-7c3d-8e4f-5a6b7c8d9e0f';

    private const SUBJECT_ID = '0190a2b4-0000-7000-8000-000000000001';

    public function test_the_assigned_teacher_lists_students(): void
    {
        $teacherId = UserId::random();
        $classrooms = $this->classroomsWith($teacherId);
        $item = new AccountListItem('s1', 'Bia', 'bia', false, true);

        $items = (new ListStudentsHandler($classrooms, new RosterPolicy($classrooms), $this->readerReturning([$item])))
            ->handle(new ListStudentsQuery(new Actor($teacherId->value(), Role::Teacher), self::CLASSROOM_ID));

        self::assertSame([$item], $items);
    }

    public function test_listing_a_deactivated_classroom_is_allowed_for_its_teacher(): void
    {
        $teacherId = UserId::random();
        $classrooms = $this->classroomsWith($teacherId);
        $classroom = $classrooms->findById(new ClassroomId(self::CLASSROOM_ID));
        self::assertNotNull($classroom);
        $classroom->deactivate(new DateTimeImmutable);
        $classrooms->save($classroom);

        $items = (new ListStudentsHandler($classrooms, new RosterPolicy($classrooms), $this->readerReturning([])))
            ->handle(new ListStudentsQuery(new Actor($teacherId->value(), Role::Teacher), self::CLASSROOM_ID));

        self::assertSame([], $items);
    }

    public function test_an_unrelated_teacher_is_denied(): void
    {
        $classrooms = $this->classroomsWith(UserId::random());

        $this->expectException(AccessDeniedException::class);

        (new ListStudentsHandler($classrooms, new RosterPolicy($classrooms), $this->readerReturning([])))
            ->handle(new ListStudentsQuery(new Actor(UserId::random()->value(), Role::Teacher), self::CLASSROOM_ID));
    }

    public function test_an_unknown_classroom_is_not_found(): void
    {
        $classrooms = $this->classrooms();

        $this->expectException(ClassroomNotFoundException::class);

        (new ListStudentsHandler($classrooms, new RosterPolicy($classrooms), $this->readerReturning([])))
            ->handle(new ListStudentsQuery(new Actor('a', Role::Admin), self::CLASSROOM_ID));
    }

    private function classroomsWith(UserId $teacherId): InMemoryClassroomRepository
    {
        $classrooms = $this->classrooms();
        $classroom = Classroom::create(new ClassroomId(self::CLASSROOM_ID), new ClassroomName('Química 1'), self::SUBJECT_ID);
        $classroom->assignTeachers([User::staff($teacherId, 'Ana', new Email('ana@escola.br'), $this->hash(), Role::Teacher, false)]);
        $classrooms->save($classroom);

        return $classrooms;
    }

    /** @param  list<AccountListItem>  $items */
    private function readerReturning(array $items): AccountListReader
    {
        return new class($items) implements AccountListReader
        {
            /** @param  list<AccountListItem>  $items */
            public function __construct(private array $items) {}

            public function teachers(): array
            {
                return [];
            }

            public function studentsOf(string $classroomId): array
            {
                return $this->items;
            }
        };
    }

    private function hash(): HashedPassword
    {
        return new HashedPassword('$2y$04$x');
    }
}
