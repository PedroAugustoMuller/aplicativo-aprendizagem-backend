<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Command\SetTeacherActive;

use App\Modules\Identity\Application\DTO\AccountView;
use App\Modules\Identity\Application\Port\TokenRevoker;
use App\Modules\Identity\Application\Port\TransactionManager;
use App\Modules\Identity\Domain\Exception\AccessDeniedException;
use App\Modules\Identity\Domain\Exception\TeacherNotFoundException;
use App\Modules\Identity\Domain\Repository\UserRepository;
use App\Modules\Identity\Domain\ValueObject\UserId;
use DateTimeImmutable;

final readonly class SetTeacherActiveHandler
{
    public function __construct(
        private UserRepository $users,
        private TokenRevoker $tokens,
        private TransactionManager $transactions,
    ) {}

    public function handle(SetTeacherActiveCommand $command): AccountView
    {
        if (! $command->actor->isAdmin()) {
            throw new AccessDeniedException;
        }

        $id = new UserId($command->id);
        $user = $this->users->findById($id);

        if ($user === null || ! $user->role()->isStaff()) {
            throw new TeacherNotFoundException;
        }

        // An admin locking themself out has no one left to undo it.
        if ($command->actor->userId === $command->id && ! $command->active) {
            throw new AccessDeniedException;
        }

        return $this->transactions->run(function () use ($user, $command): AccountView {
            if ($command->active) {
                $user->reactivate();
            } else {
                $user->deactivate(new DateTimeImmutable);
            }

            $this->users->save($user);

            if (! $command->active) {
                $this->tokens->revokeAll($user->id());
            }

            return AccountView::of($user, null);
        });
    }
}
