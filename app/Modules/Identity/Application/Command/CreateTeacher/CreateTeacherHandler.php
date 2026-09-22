<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Command\CreateTeacher;

use App\Modules\Identity\Application\DTO\AccountView;
use App\Modules\Identity\Application\Port\CredentialVault;
use App\Modules\Identity\Application\Port\TransactionManager;
use App\Modules\Identity\Application\Service\PasswordIssuer;
use App\Modules\Identity\Domain\Entity\User;
use App\Modules\Identity\Domain\Exception\AccessDeniedException;
use App\Modules\Identity\Domain\Exception\EmailAlreadyTakenException;
use App\Modules\Identity\Domain\Repository\UserRepository;
use App\Modules\Identity\Domain\ValueObject\Email;
use App\Modules\Identity\Domain\ValueObject\HashedPassword;
use App\Modules\Identity\Domain\ValueObject\UserId;
use App\Shared\Domain\Auth\Role;
use App\Shared\Domain\Exception\IdempotencyConflictException;

final readonly class CreateTeacherHandler
{
    public function __construct(
        private UserRepository $users,
        private PasswordIssuer $passwords,
        private CredentialVault $vault,
        private TransactionManager $transactions,
    ) {}

    public function handle(CreateTeacherCommand $command): AccountView
    {
        if (! $command->actor->isAdmin()) {
            throw new AccessDeniedException;
        }

        $id = new UserId($command->id);
        $email = new Email($command->email);
        $name = trim($command->name);
        $existing = $this->users->findById($id);

        if ($existing !== null) {
            if ($existing->name() !== $name || $existing->email()?->equals($email) !== true) {
                throw new IdempotencyConflictException;
            }

            return AccountView::of($existing, $this->vault->reveal($existing->id()));
        }

        if ($this->users->emailExists($email)) {
            throw new EmailAlreadyTakenException($email->value());
        }

        // The hash is replaced by PasswordIssuer immediately; this placeholder only
        // satisfies the entity's non-empty invariant and is never persisted.
        $teacher = User::staff($id, $name, $email, new HashedPassword('pending'), Role::Teacher, true);

        return $this->transactions->run(function () use ($teacher): AccountView {
            $issued = $this->passwords->issueFor($teacher);
            $this->users->save($teacher);
            $this->passwords->remember($teacher, $issued);

            return AccountView::of($teacher, $issued->plain);
        });
    }
}
