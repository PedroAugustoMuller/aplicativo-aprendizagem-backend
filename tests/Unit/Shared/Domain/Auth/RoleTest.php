<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\Auth;

use App\Shared\Domain\Auth\Role;
use PHPUnit\Framework\TestCase;

final class RoleTest extends TestCase
{
    public function test_admins_and_teachers_are_staff_students_are_not(): void
    {
        self::assertTrue(Role::Admin->isStaff());
        self::assertTrue(Role::Teacher->isStaff());
        self::assertFalse(Role::Student->isStaff());
    }
}
