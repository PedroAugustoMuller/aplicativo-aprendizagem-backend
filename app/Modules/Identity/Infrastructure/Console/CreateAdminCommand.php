<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Console;

use App\Modules\Identity\Application\Port\TransactionManager;
use App\Modules\Identity\Application\Service\PasswordIssuer;
use App\Modules\Identity\Domain\Entity\User;
use App\Modules\Identity\Domain\Repository\UserRepository;
use App\Modules\Identity\Domain\ValueObject\Email;
use App\Modules\Identity\Domain\ValueObject\HashedPassword;
use App\Modules\Identity\Domain\ValueObject\UserId;
use App\Modules\Identity\Infrastructure\Persistence\UserModel;
use App\Shared\Domain\Auth\Role;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * The one way to bootstrap the very first account: every other admin, teacher and
 * student is created by an existing admin through the API. Refuses to run once
 * any admin exists, so it cannot be used to mint a second one by mistake.
 */
final class CreateAdminCommand extends Command
{
    protected $signature = 'identity:create-admin {name} {email}';

    protected $description = 'Create the first admin. Refuses to run once any admin exists.';

    public function handle(UserRepository $users, PasswordIssuer $passwords, TransactionManager $transactions): int
    {
        if (UserModel::query()->where('role', Role::Admin->value)->exists()) {
            $this->components->error('An admin already exists.');

            return self::FAILURE;
        }

        try {
            $email = new Email((string) $this->argument('email'));
        } catch (InvalidArgumentException) {
            $this->components->error('Invalid email.');

            return self::FAILURE;
        }

        $admin = User::staff(UserId::random(), (string) $this->argument('name'), $email, new HashedPassword('pending'), Role::Admin, true);

        $plain = $transactions->run(function () use ($admin, $users, $passwords): string {
            $issued = $passwords->issueFor($admin);
            $users->save($admin);
            $passwords->remember($admin, $issued);

            return $issued->plain;
        });

        $this->components->info('Temporary password: '.$plain);

        return self::SUCCESS;
    }
}
