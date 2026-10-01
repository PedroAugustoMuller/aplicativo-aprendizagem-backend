<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity\Support;

use App\Modules\Identity\Application\Port\TokenRevoker;
use App\Modules\Identity\Domain\ValueObject\UserId;

final class RecordingTokenRevoker implements TokenRevoker
{
    /** @var list<string> */
    public array $allCalls = [];

    /** @var list<array{string, string}> */
    public array $exceptCalls = [];

    public function revokeAll(UserId $id): void
    {
        $this->allCalls[] = $id->value();
    }

    public function revokeAllExcept(UserId $id, string $keepTokenId): void
    {
        $this->exceptCalls[] = [$id->value(), $keepTokenId];
    }
}
