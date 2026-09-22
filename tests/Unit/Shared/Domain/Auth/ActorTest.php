<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\Auth;

use App\Shared\Domain\Auth\Actor;
use App\Shared\Domain\Auth\Role;
use PHPUnit\Framework\TestCase;

final class ActorTest extends TestCase
{
    public function test_an_admin_is_also_staff(): void
    {
        $actor = new Actor('u-1', Role::Admin);

        self::assertTrue($actor->isAdmin());
        self::assertTrue($actor->isStaff());
        self::assertFalse($actor->isStudent());
    }

    public function test_a_student_is_not_staff(): void
    {
        $actor = new Actor('u-2', Role::Student);

        self::assertFalse($actor->isStaff());
        self::assertTrue($actor->isStudent());
    }
}
