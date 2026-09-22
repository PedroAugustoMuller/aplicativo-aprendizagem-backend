<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity\Application;

use App\Modules\Identity\Application\DTO\IssuedCredential;
use App\Modules\Identity\Application\Query\ListCredentials\ListCredentialsHandler;
use App\Modules\Identity\Application\Query\ListCredentials\ListCredentialsQuery;
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
use PHPUnit\Framework\TestCase;
use Tests\Unit\Modules\Identity\Support\IdentityFakes;
use Tests\Unit\Modules\Identity\Support\InMemoryClassroomRepository;

final class ListCredentialsHandlerTest extends TestCase
{
    use IdentityFakes;

    private const CLASSROOM_ID = '0199c0de-1a2b-7c3d-8e4f-5a6b7c8d9e0f';

    private const SUBJECT_ID = '0190a2b4-0000-7000-8000-000000000001';

    public function test_it_lists_only_pending_students_with_a_vault_entry(): void
    {
        $teacherId = UserId::random();
        $classrooms = $this->classroomsWith($teacherId);

        // Pending and issued through the normal flow: has a vault entry, listed.
        $pendingWithSlip = new AccountListItem(UserId::random()->value(), 'Ana Souza', 'ana.souza', true, true);
        // Already changed their password: not listed, regardless of the vault.
        $changedAlready = new AccountListItem(UserId::random()->value(), 'Beto Lima', 'beto.lima', false, true);
        // Still pending but with no vault entry (e.g. a row seeded directly, bypassing
        // credential issuance): not listed, there is no password to print.
        $pendingWithoutSlip = new AccountListItem(UserId::random()->value(), 'Caio Nunes', 'caio.nunes', true, true);
        // Pending, has a vault entry, but the account was deactivated: not listed —
        // a printed slip must never hand out a password for a deactivated account.
        $pendingButDeactivated = new AccountListItem(UserId::random()->value(), 'Duda Reis', 'duda.reis', true, false);

        $vault = $this->vault();
        $vault->store(new UserId($pendingWithSlip->id), 'Abc12345');
        $vault->store(new UserId($changedAlready->id), 'ShouldNotAppear1');
        $vault->store(new UserId($pendingButDeactivated->id), 'ShouldNotAppear2');

        $handler = new ListCredentialsHandler($classrooms, new RosterPolicy($classrooms), $this->readerReturning([
            $pendingWithSlip, $changedAlready, $pendingWithoutSlip, $pendingButDeactivated,
        ]), $vault);

        $slips = $handler->handle(new ListCredentialsQuery(new Actor($teacherId->value(), Role::Teacher), self::CLASSROOM_ID));

        self::assertEquals([
            new IssuedCredential($pendingWithSlip->id, 'Ana Souza', 'ana.souza', 'Abc12345'),
        ], $slips);
    }

    public function test_an_unrelated_teacher_is_denied(): void
    {
        $classrooms = $this->classroomsWith(UserId::random());

        $this->expectException(AccessDeniedException::class);

        (new ListCredentialsHandler($classrooms, new RosterPolicy($classrooms), $this->readerReturning([]), $this->vault()))
            ->handle(new ListCredentialsQuery(new Actor(UserId::random()->value(), Role::Teacher), self::CLASSROOM_ID));
    }

    public function test_an_unknown_classroom_is_not_found(): void
    {
        $classrooms = $this->classrooms();

        $this->expectException(ClassroomNotFoundException::class);

        (new ListCredentialsHandler($classrooms, new RosterPolicy($classrooms), $this->readerReturning([]), $this->vault()))
            ->handle(new ListCredentialsQuery(new Actor('a', Role::Admin), self::CLASSROOM_ID));
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
