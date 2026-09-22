<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Command\AuthenticateUser;

use App\Modules\Identity\Application\DTO\AuthenticatedUser;
use App\Modules\Identity\Application\Port\PasswordHasher;
use App\Modules\Identity\Application\Port\TokenIssuer;
use App\Modules\Identity\Domain\Entity\User;
use App\Modules\Identity\Domain\Exception\InvalidCredentialsException;
use App\Modules\Identity\Domain\Repository\UserRepository;
use App\Modules\Identity\Domain\ValueObject\Email;
use App\Modules\Identity\Domain\ValueObject\Username;
use InvalidArgumentException;

final readonly class AuthenticateUserHandler
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $hasher,
        private TokenIssuer $tokens,
    ) {}

    public function handle(AuthenticateUserCommand $command): AuthenticatedUser
    {
        $user = $this->lookup($command->login);

        // Always verify, even with no match or an inactive account: an early
        // return would let an attacker tell accounts apart by response time.
        $matches = $this->hasher->verify(
            $command->password,
            $user?->password() ?? $this->hasher->dummyHash(),
        );

        if (! $user instanceof User || ! $matches || ! $user->isActive()) {
            throw new InvalidCredentialsException;
        }

        return new AuthenticatedUser(
            id: $user->id()->value(),
            name: $user->name(),
            login: $user->login(),
            role: $user->role()->value,
            mustChangePassword: $user->mustChangePassword(),
            token: $this->tokens->issue($user->id()),
        );
    }

    private function lookup(string $login): ?User
    {
        // Only the value-object construction is guarded, for the same reason as
        // before: a repository bug must surface as a 500, not a routine 401.
        if (str_contains($login, '@')) {
            try {
                $email = new Email($login);
            } catch (InvalidArgumentException) {
                return null;
            }

            return $this->users->findByEmail($email);
        }

        try {
            $username = new Username($login);
        } catch (InvalidArgumentException) {
            return null;
        }

        return $this->users->findByUsername($username);
    }
}
