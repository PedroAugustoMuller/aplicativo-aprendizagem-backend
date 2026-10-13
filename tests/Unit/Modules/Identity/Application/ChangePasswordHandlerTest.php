<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity\Application;

use App\Modules\Identity\Application\Command\ChangePassword\ChangePasswordCommand;
use App\Modules\Identity\Application\Command\ChangePassword\ChangePasswordHandler;
use App\Modules\Identity\Domain\Entity\User;
use App\Modules\Identity\Domain\Exception\CurrentPasswordInvalidException;
use App\Modules\Identity\Domain\Exception\NewPasswordUnchangedException;
use App\Modules\Identity\Domain\ValueObject\HashedPassword;
use App\Modules\Identity\Domain\ValueObject\UserId;
use App\Modules\Identity\Domain\ValueObject\Username;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Modules\Identity\Support\IdentityFakes;

final class ChangePasswordHandlerTest extends TestCase
{
    use IdentityFakes;

    public function test_it_changes_the_password_clears_the_vault_and_keeps_only_the_current_token(): void
    {
        $user = User::student(UserId::random(), 'Bia', new Username('bia'), new HashedPassword('hash:temp1234'), true);
        $users = $this->users($user);
        $vault = $this->vault();
        $vault->store($user->id(), 'temp1234');
        $revoker = $this->revoker();

        (new ChangePasswordHandler($users, $this->hasher(), $vault, $revoker, $this->transactions()))
            ->handle(new ChangePasswordCommand($user->id()->value(), 'temp1234', 'my-new-pass', 'token-7'));

        $saved = $users->findById($user->id());
        self::assertNotNull($saved);
        self::assertFalse($saved->mustChangePassword());
        self::assertSame('hash:my-new-pass', $saved->password()->value());
        self::assertNull($vault->reveal($user->id()));
        self::assertSame([[$user->id()->value(), 'token-7']], $revoker->exceptCalls);
    }

    public function test_a_wrong_current_password_changes_nothing(): void
    {
        $user = User::student(UserId::random(), 'Bia', new Username('bia'), new HashedPassword('hash:mine1234'), false);
        $users = $this->users($user);

        $this->expectException(CurrentPasswordInvalidException::class);

        (new ChangePasswordHandler($users, $this->hasher(), $this->vault(), $this->revoker(), $this->transactions()))
            ->handle(new ChangePasswordCommand($user->id()->value(), 'wrong', 'my-new-pass', 'token-7'));
    }

    public function test_a_pending_user_changes_without_the_current_password(): void
    {
        $user = User::student(UserId::random(), 'Bia', new Username('bia'), new HashedPassword('hash:temp1234'), true);
        $users = $this->users($user);

        (new ChangePasswordHandler($users, $this->hasher(), $this->vault(), $this->revoker(), $this->transactions()))
            ->handle(new ChangePasswordCommand($user->id()->value(), null, 'my-new-pass', 'token-7'));

        $saved = $users->findById($user->id());
        self::assertNotNull($saved);
        self::assertFalse($saved->mustChangePassword());
        self::assertSame('hash:my-new-pass', $saved->password()->value());
    }

    public function test_a_pending_user_s_wrong_current_password_is_ignored(): void
    {
        $user = User::student(UserId::random(), 'Bia', new Username('bia'), new HashedPassword('hash:temp1234'), true);
        $users = $this->users($user);

        (new ChangePasswordHandler($users, $this->hasher(), $this->vault(), $this->revoker(), $this->transactions()))
            ->handle(new ChangePasswordCommand($user->id()->value(), 'wrong', 'my-new-pass', 'token-7'));

        self::assertSame('hash:my-new-pass', $users->findById($user->id())?->password()->value());
    }

    public function test_a_voluntary_change_without_the_current_password_is_refused(): void
    {
        $user = User::student(UserId::random(), 'Bia', new Username('bia'), new HashedPassword('hash:mine1234'), false);

        $this->expectException(CurrentPasswordInvalidException::class);

        (new ChangePasswordHandler($this->users($user), $this->hasher(), $this->vault(), $this->revoker(), $this->transactions()))
            ->handle(new ChangePasswordCommand($user->id()->value(), null, 'my-new-pass', 'token-7'));
    }

    public function test_a_voluntary_change_with_the_right_current_password_goes_through(): void
    {
        $user = User::student(UserId::random(), 'Bia', new Username('bia'), new HashedPassword('hash:mine1234'), false);
        $users = $this->users($user);

        (new ChangePasswordHandler($users, $this->hasher(), $this->vault(), $this->revoker(), $this->transactions()))
            ->handle(new ChangePasswordCommand($user->id()->value(), 'mine1234', 'my-new-pass', 'token-7'));

        self::assertSame('hash:my-new-pass', $users->findById($user->id())?->password()->value());
    }

    public function test_a_pending_user_cannot_keep_the_temporary_password(): void
    {
        $user = User::student(UserId::random(), 'Bia', new Username('bia'), new HashedPassword('hash:temp1234'), true);
        $users = $this->users($user);

        try {
            (new ChangePasswordHandler($users, $this->hasher(), $this->vault(), $this->revoker(), $this->transactions()))
                ->handle(new ChangePasswordCommand($user->id()->value(), null, 'temp1234', 'token-7'));
            self::fail('Expected NewPasswordUnchangedException.');
        } catch (NewPasswordUnchangedException) {
            self::assertTrue($users->findById($user->id())?->mustChangePassword());
        }
    }

    public function test_a_voluntary_user_cannot_reuse_the_current_password(): void
    {
        $user = User::student(UserId::random(), 'Bia', new Username('bia'), new HashedPassword('hash:mine1234'), false);

        $this->expectException(NewPasswordUnchangedException::class);

        (new ChangePasswordHandler($this->users($user), $this->hasher(), $this->vault(), $this->revoker(), $this->transactions()))
            ->handle(new ChangePasswordCommand($user->id()->value(), 'mine1234', 'mine1234', 'token-7'));
    }

    public function test_a_wrong_current_password_wins_over_an_unchanged_new_one(): void
    {
        // Otherwise "unchanged" would confirm a guessed password without knowing the current one.
        $user = User::student(UserId::random(), 'Bia', new Username('bia'), new HashedPassword('hash:mine1234'), false);

        $this->expectException(CurrentPasswordInvalidException::class);

        (new ChangePasswordHandler($this->users($user), $this->hasher(), $this->vault(), $this->revoker(), $this->transactions()))
            ->handle(new ChangePasswordCommand($user->id()->value(), 'wrong', 'mine1234', 'token-7'));
    }
}
