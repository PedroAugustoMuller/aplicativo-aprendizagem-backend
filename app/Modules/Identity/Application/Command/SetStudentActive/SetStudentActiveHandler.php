<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Command\SetStudentActive;

use App\Modules\Identity\Application\DTO\AccountView;
use App\Modules\Identity\Application\Port\TokenRevoker;
use App\Modules\Identity\Application\Port\TransactionManager;
use App\Modules\Identity\Domain\Exception\AccessDeniedException;
use App\Modules\Identity\Domain\Exception\StudentNotFoundException;
use App\Modules\Identity\Domain\Policy\RosterPolicy;
use App\Modules\Identity\Domain\Repository\UserRepository;
use App\Modules\Identity\Domain\ValueObject\UserId;
use App\Shared\Domain\Auth\Role;
use DateTimeImmutable;

final readonly class SetStudentActiveHandler
{
    public function __construct(
        private UserRepository $users,
        private RosterPolicy $policy,
        private TokenRevoker $tokens,
        private TransactionManager $transactions,
    ) {}

    public function handle(SetStudentActiveCommand $command): AccountView
    {
        $id = new UserId($command->studentId);
        $student = $this->users->findById($id);

        if ($student === null || $student->role() !== Role::Student) {
            throw new StudentNotFoundException;
        }

        if (! $this->policy->canManageStudent($command->actor, $id)) {
            throw new AccessDeniedException;
        }

        return $this->transactions->run(function () use ($student, $command): AccountView {
            if ($command->active) {
                $student->reactivate();
            } else {
                $student->deactivate(new DateTimeImmutable);
            }

            $this->users->save($student);

            if (! $command->active) {
                $this->tokens->revokeAll($student->id());
            }

            return AccountView::of($student, null);
        });
    }
}
