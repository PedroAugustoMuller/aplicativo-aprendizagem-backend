<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Auth;

use App\Modules\Identity\Application\Port\CredentialVault;
use App\Modules\Identity\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\EloquentAttribute;
use Illuminate\Contracts\Encryption\StringEncrypter as Encrypter;
use Illuminate\Support\Facades\DB;

final class EncryptedCredentialVault implements CredentialVault
{
    public function __construct(private readonly Encrypter $encrypter) {}

    public function store(UserId $id, string $plainPassword): void
    {
        DB::table('pending_credentials')->upsert(
            [[
                'user_id' => $id->value(),
                'password_encrypted' => $this->encrypter->encryptString($plainPassword),
                'created_at' => now(),
            ]],
            ['user_id'],
            ['password_encrypted', 'created_at'],
        );
    }

    public function reveal(UserId $id): ?string
    {
        $value = DB::table('pending_credentials')->where('user_id', $id->value())->value('password_encrypted');

        return is_string($value) ? $this->encrypter->decryptString($value) : null;
    }

    /**
     * @param  list<UserId>  $ids
     * @return array<string, string>
     */
    public function revealMany(array $ids): array
    {
        $rows = DB::table('pending_credentials')
            ->whereIn('user_id', array_map(fn (UserId $id): string => $id->value(), $ids))
            ->pluck('password_encrypted', 'user_id');

        $revealed = [];
        foreach ($rows as $userId => $encrypted) {
            $revealed[(string) $userId] = $this->encrypter->decryptString(
                EloquentAttribute::string($encrypted, 'pending_credentials.password_encrypted'),
            );
        }

        return $revealed;
    }

    public function forget(UserId $id): void
    {
        DB::table('pending_credentials')->where('user_id', $id->value())->delete();
    }
}
