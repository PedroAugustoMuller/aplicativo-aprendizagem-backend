<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity\Support;

use App\Modules\Identity\Domain\Entity\User;

/** In-memory fakes reused by every Identity unit test. */
trait IdentityFakes
{
    private function hasher(): FakePasswordHasher
    {
        return new FakePasswordHasher;
    }

    private function users(User ...$seed): InMemoryUserRepository
    {
        $repo = new InMemoryUserRepository;

        foreach ($seed as $user) {
            $repo->save($user);
        }

        return $repo;
    }

    private function vault(): InMemoryCredentialVault
    {
        return new InMemoryCredentialVault;
    }

    private function revoker(): RecordingTokenRevoker
    {
        return new RecordingTokenRevoker;
    }

    private function transactions(): ImmediateTransactionManager
    {
        return new ImmediateTransactionManager;
    }

    private function classrooms(): InMemoryClassroomRepository
    {
        return new InMemoryClassroomRepository;
    }

    /** @param  list<string>  $activeIds */
    private function subjectCatalog(array $activeIds): FakeSubjectCatalog
    {
        return new FakeSubjectCatalog($activeIds);
    }
}
