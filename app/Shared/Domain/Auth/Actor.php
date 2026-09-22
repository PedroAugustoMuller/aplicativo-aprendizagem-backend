<?php

declare(strict_types=1);

namespace App\Shared\Domain\Auth;

/**
 * Who is making this request. Built per request by the controller and passed as an
 * argument — never bound in the container, so it cannot leak between requests.
 */
final readonly class Actor
{
    public function __construct(
        public string $userId,
        public Role $role,
    ) {}

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isStaff(): bool
    {
        return $this->role->isStaff();
    }

    public function isStudent(): bool
    {
        return $this->role === Role::Student;
    }
}
