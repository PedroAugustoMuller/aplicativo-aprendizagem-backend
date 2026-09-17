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
        $user = $this->lookup($command->email);

        // Always verify, even with no match: an early return would let an attacker
        // distinguish registered emails by response time.
        $matches = $this->hasher->verify(
            $command->password,
            $user?->password() ?? $this->hasher->dummyHash(),
        );

        if (! $user instanceof User || ! $matches) {
            throw new InvalidCredentialsException;
        }

        return new AuthenticatedUser(
            id: $user->id()->value(),
            name: $user->name(),
            email: $user->email()->value(),
            token: $this->tokens->issue($user->id()),
        );
    }

    private function lookup(string $email): ?User
    {
        // The try wraps ONLY the Email construction. If it also wrapped the
        // repository call, an InvalidArgumentException thrown by a future
        // repository bug would be silently reinterpreted as "no such user" and
        // returned to the client as a routine 401 — hiding a real defect that
        // should have surfaced as system.unexpected_error.
        try {
            $address = new Email($email);
        } catch (InvalidArgumentException) {
            return null;
        }

        return $this->users->findByEmail($address);
    }
}
