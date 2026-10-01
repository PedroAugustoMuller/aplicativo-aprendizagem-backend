<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Command\ResetStudentPassword;

use App\Modules\Identity\Application\DTO\AccountView;
use App\Modules\Identity\Application\Port\TokenRevoker;
use App\Modules\Identity\Application\Port\TransactionManager;
use App\Modules\Identity\Application\Service\PasswordIssuer;
use App\Modules\Identity\Domain\Exception\AccessDeniedException;
use App\Modules\Identity\Domain\Exception\StudentNotFoundException;
use App\Modules\Identity\Domain\Policy\RosterPolicy;
use App\Modules\Identity\Domain\Repository\UserRepository;
use App\Modules\Identity\Domain\ValueObject\UserId;
use App\Shared\Domain\Auth\Role;

final readonly class ResetStudentPasswordHandler
{
    public function __construct(
        private UserRepository $users,
        private RosterPolicy $policy,
        private PasswordIssuer $passwords,
        private TokenRevoker $tokens,
        private TransactionManager $transactions,
    ) {}

    public function handle(ResetStudentPasswordCommand $command): AccountView
    {
        $id = new UserId($command->studentId);
        $student = $this->users->findById($id);

        if ($student === null || $student->role() !== Role::Student) {
            throw new StudentNotFoundException;
        }

        if (! $this->policy->canManageStudent($command->actor, $id)) {
            throw new AccessDeniedException;
        }

        return $this->transactions->run(function () use ($student): AccountView {
            $issued = $this->passwords->issueFor($student);
            $this->users->save($student);
            $this->passwords->remember($student, $issued);

            // A replay must not log the student out again: only a fresh reset does.
            if ($issued->isNew) {
                $this->tokens->revokeAll($student->id());
            }

            return AccountView::of($student, $issued->plain);
        });
    }
}
