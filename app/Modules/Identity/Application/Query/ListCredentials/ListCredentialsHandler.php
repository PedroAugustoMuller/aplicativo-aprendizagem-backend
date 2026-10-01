<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Query\ListCredentials;

use App\Modules\Identity\Application\DTO\IssuedCredential;
use App\Modules\Identity\Application\Port\CredentialVault;
use App\Modules\Identity\Application\Query\ListTeachers\AccountListItem;
use App\Modules\Identity\Application\Query\ListTeachers\AccountListReader;
use App\Modules\Identity\Domain\Exception\AccessDeniedException;
use App\Modules\Identity\Domain\Exception\ClassroomNotFoundException;
use App\Modules\Identity\Domain\Policy\RosterPolicy;
use App\Modules\Identity\Domain\Repository\ClassroomRepository;
use App\Modules\Identity\Domain\ValueObject\ClassroomId;
use App\Modules\Identity\Domain\ValueObject\UserId;

/** Printable credential slips: pending students who still hold a temporary password. */
final readonly class ListCredentialsHandler
{
    public function __construct(
        private ClassroomRepository $classrooms,
        private RosterPolicy $policy,
        private AccountListReader $accounts,
        private CredentialVault $vault,
    ) {}

    /** @return list<IssuedCredential> */
    public function handle(ListCredentialsQuery $query): array
    {
        $classroom = $this->classrooms->findById(new ClassroomId($query->classroomId))
            ?? throw new ClassroomNotFoundException;

        if (! $this->policy->canManageClassroom($query->actor, $classroom)) {
            throw new AccessDeniedException;
        }

        $pending = array_values(array_filter(
            $this->accounts->studentsOf($query->classroomId),
            fn (AccountListItem $s): bool => $s->mustChangePassword && $s->active,
        ));

        $passwords = $this->vault->revealMany(array_map(fn (AccountListItem $s): UserId => new UserId($s->id), $pending));

        $slips = [];
        foreach ($pending as $student) {
            if (isset($passwords[$student->id])) {
                $slips[] = new IssuedCredential($student->id, $student->name, $student->login, $passwords[$student->id]);
            }
        }

        return $slips;
    }
}
