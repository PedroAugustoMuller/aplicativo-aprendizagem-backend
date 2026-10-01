<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Command\ResetTeacherPassword;

use App\Modules\Identity\Application\DTO\AccountView;
use App\Modules\Identity\Application\Port\TokenRevoker;
use App\Modules\Identity\Application\Port\TransactionManager;
use App\Modules\Identity\Application\Service\PasswordIssuer;
use App\Modules\Identity\Domain\Exception\AccessDeniedException;
use App\Modules\Identity\Domain\Exception\TeacherNotFoundException;
use App\Modules\Identity\Domain\Repository\UserRepository;
use App\Modules\Identity\Domain\ValueObject\UserId;

final readonly class ResetTeacherPasswordHandler
{
    public function __construct(
        private UserRepository $users,
        private PasswordIssuer $passwords,
        private TokenRevoker $tokens,
        private TransactionManager $transactions,
    ) {}

    public function handle(ResetTeacherPasswordCommand $command): AccountView
    {
        if (! $command->actor->isAdmin()) {
            throw new AccessDeniedException;
        }

        $id = new UserId($command->id);
        $user = $this->users->findById($id);

        if ($user === null || ! $user->role()->isStaff()) {
            throw new TeacherNotFoundException;
        }

        return $this->transactions->run(function () use ($user): AccountView {
            $issued = $this->passwords->issueFor($user);
            $this->users->save($user);
            $this->passwords->remember($user, $issued);

            // A replay must not log the teacher out again: only a fresh reset does.
            if ($issued->isNew) {
                $this->tokens->revokeAll($user->id());
            }

            return AccountView::of($user, $issued->plain);
        });
    }
}
