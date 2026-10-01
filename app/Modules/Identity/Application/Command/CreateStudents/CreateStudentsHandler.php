<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Command\CreateStudents;

use App\Modules\Identity\Application\DTO\AccountView;
use App\Modules\Identity\Application\Port\CredentialVault;
use App\Modules\Identity\Application\Port\TransactionManager;
use App\Modules\Identity\Application\Service\PasswordIssuer;
use App\Modules\Identity\Domain\Entity\User;
use App\Modules\Identity\Domain\Exception\AccessDeniedException;
use App\Modules\Identity\Domain\Exception\ClassroomNotFoundException;
use App\Modules\Identity\Domain\Policy\RosterPolicy;
use App\Modules\Identity\Domain\Repository\ClassroomRepository;
use App\Modules\Identity\Domain\Repository\UserRepository;
use App\Modules\Identity\Domain\Service\UsernameGenerator;
use App\Modules\Identity\Domain\ValueObject\ClassroomId;
use App\Modules\Identity\Domain\ValueObject\HashedPassword;
use App\Modules\Identity\Domain\ValueObject\UserId;
use App\Shared\Domain\Auth\Role;
use App\Shared\Domain\Exception\IdempotencyConflictException;

/**
 * Bulk-creates students for one classroom, enrolling each. All-or-nothing: the
 * whole batch is validated before anything is written, because a teacher pasting
 * a class list expects every row to land or none of them to.
 *
 * Username collision is checked against the repository plus the names already
 * generated earlier in this same batch — two "Ana Souza" rows in one paste must
 * not both compute "ana.souza". A collision between two *concurrent* requests
 * (two teachers submitting "Ana Souza" at the same moment) is not covered here:
 * both can compute the same candidate, and the `users.username` unique index
 * makes the loser's transaction fail with a QueryException, surfaced as a 500.
 * Accepted at this scale (one school); no retry logic added for it.
 */
final readonly class CreateStudentsHandler
{
    public function __construct(
        private ClassroomRepository $classrooms,
        private UserRepository $users,
        private RosterPolicy $policy,
        private UsernameGenerator $usernames,
        private PasswordIssuer $passwords,
        private CredentialVault $vault,
        private TransactionManager $transactions,
    ) {}

    /** @return list<AccountView> */
    public function handle(CreateStudentsCommand $command): array
    {
        $classroom = $this->classrooms->findById(new ClassroomId($command->classroomId));
        if ($classroom === null || ! $classroom->isActive()) {
            throw new ClassroomNotFoundException;
        }

        if (! $this->policy->canManageClassroom($command->actor, $classroom)) {
            throw new AccessDeniedException;
        }

        // Validate the whole batch before writing anything: all-or-nothing is a
        // promise to the teacher, not only a database transaction.
        $plan = [];
        $claimed = [];
        foreach ($command->students as $row) {
            $id = new UserId($row['id']);
            $name = trim($row['name']);
            $existing = $this->users->findById($id);

            if ($existing !== null) {
                if ($existing->role() !== Role::Student || $existing->name() !== $name) {
                    throw new IdempotencyConflictException;
                }
                $plan[] = ['existing' => $existing];

                continue;
            }

            $username = $this->usernames->generate(
                $name,
                fn (string $u): bool => isset($claimed[$u]) || $this->users->usernameExists($u),
            );
            $claimed[$username->value()] = true;
            $plan[] = ['new' => User::student($id, $name, $username, new HashedPassword('pending'), true)];
        }

        return $this->transactions->run(function () use ($plan, $classroom): array {
            $views = [];
            foreach ($plan as $step) {
                if (isset($step['existing'])) {
                    $student = $step['existing'];
                    $views[] = AccountView::of($student, $this->vault->reveal($student->id()));
                    $classroom->enrol($student);

                    continue;
                }

                $student = $step['new'];
                $issued = $this->passwords->issueFor($student);
                $this->users->save($student);
                $this->passwords->remember($student, $issued);
                $classroom->enrol($student);
                $views[] = AccountView::of($student, $issued->plain);
            }

            $this->classrooms->save($classroom);

            return $views;
        });
    }
}
