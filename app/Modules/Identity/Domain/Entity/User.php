<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Entity;

use App\Modules\Identity\Domain\ValueObject\Email;
use App\Modules\Identity\Domain\ValueObject\HashedPassword;
use App\Modules\Identity\Domain\ValueObject\UserId;
use App\Modules\Identity\Domain\ValueObject\Username;
use App\Shared\Domain\Auth\Role;
use DateTimeImmutable;
use InvalidArgumentException;

final class User
{
    private function __construct(
        private readonly UserId $id,
        private readonly string $name,
        private readonly Role $role,
        private readonly ?Email $email,
        private readonly ?Username $username,
        private HashedPassword $password,
        private bool $mustChangePassword,
        private ?DateTimeImmutable $deactivatedAt,
    ) {
        if (trim($name) === '') {
            throw new InvalidArgumentException('User name cannot be empty.');
        }

        // Staff sign in with an email; students with a username and no email (LGPD:
        // no contact data for minors). The database check constraint mirrors this.
        if ($role === Role::Student && ($username === null || $email !== null)) {
            throw new InvalidArgumentException('A student has a username and no email.');
        }

        if ($role->isStaff() && ($email === null || $username !== null)) {
            throw new InvalidArgumentException('Staff have an email and no username.');
        }
    }

    public static function staff(UserId $id, string $name, Email $email, HashedPassword $password, Role $role, bool $mustChangePassword): self
    {
        return new self($id, trim($name), $role, $email, null, $password, $mustChangePassword, null);
    }

    public static function student(UserId $id, string $name, Username $username, HashedPassword $password, bool $mustChangePassword): self
    {
        return new self($id, trim($name), Role::Student, null, $username, $password, $mustChangePassword, null);
    }

    /** Rehydration from persistence. Same invariants, full state. */
    public static function restore(
        UserId $id,
        string $name,
        Role $role,
        ?Email $email,
        ?Username $username,
        HashedPassword $password,
        bool $mustChangePassword,
        ?DateTimeImmutable $deactivatedAt,
    ): self {
        return new self($id, $name, $role, $email, $username, $password, $mustChangePassword, $deactivatedAt);
    }

    public function id(): UserId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function role(): Role
    {
        return $this->role;
    }

    public function email(): ?Email
    {
        return $this->email;
    }

    public function username(): ?Username
    {
        return $this->username;
    }

    /** What this user types in the login field. */
    public function login(): string
    {
        return $this->email?->value() ?? $this->username?->value() ?? '';
    }

    public function password(): HashedPassword
    {
        return $this->password;
    }

    public function mustChangePassword(): bool
    {
        return $this->mustChangePassword;
    }

    public function deactivatedAt(): ?DateTimeImmutable
    {
        return $this->deactivatedAt;
    }

    public function isActive(): bool
    {
        return $this->deactivatedAt === null;
    }

    public function deactivate(DateTimeImmutable $at): void
    {
        $this->deactivatedAt ??= $at;
    }

    public function reactivate(): void
    {
        $this->deactivatedAt = null;
    }

    /** A password someone else chose: the owner must replace it on next sign-in. */
    public function resetPassword(HashedPassword $temporary): void
    {
        $this->password = $temporary;
        $this->mustChangePassword = true;
    }

    public function changePassword(HashedPassword $chosen): void
    {
        $this->password = $chosen;
        $this->mustChangePassword = false;
    }
}
