<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Content\Domain;

use App\Modules\Content\Domain\Policy\SubjectPolicy;
use App\Shared\Domain\Auth\Actor;
use App\Shared\Domain\Auth\Role;
use App\Shared\Domain\Contract\TeachingAssignments;
use PHPUnit\Framework\TestCase;

final class SubjectPolicyTest extends TestCase
{
    private const CHEM = '0190a2b4-0000-7000-8000-00000000c4e1';

    private const BIO = '0190a2b4-0000-7000-8000-0000000000b1';

    public function test_view(): void
    {
        $policy = new SubjectPolicy($this->assignments(taught: ['t' => [self::CHEM]], enrolled: ['s' => [self::CHEM]]));

        self::assertTrue($policy->canView(new Actor('a', Role::Admin), self::BIO));
        self::assertTrue($policy->canView(new Actor('t', Role::Teacher), self::BIO));
        self::assertTrue($policy->canView(new Actor('s', Role::Student), self::CHEM));
        self::assertFalse($policy->canView(new Actor('s', Role::Student), self::BIO));
    }

    public function test_author(): void
    {
        $policy = new SubjectPolicy($this->assignments(taught: ['t' => [self::CHEM]], enrolled: ['s' => [self::CHEM, self::BIO]]));

        self::assertTrue($policy->canAuthor(new Actor('a', Role::Admin), self::BIO));
        self::assertTrue($policy->canAuthor(new Actor('t', Role::Teacher), self::CHEM));
        self::assertFalse($policy->canAuthor(new Actor('t', Role::Teacher), self::BIO));
        self::assertFalse($policy->canAuthor(new Actor('s', Role::Student), self::CHEM));
    }

    /**
     * @param  array<string, list<string>>  $taught
     * @param  array<string, list<string>>  $enrolled
     */
    private function assignments(array $taught, array $enrolled): TeachingAssignments
    {
        return new class($taught, $enrolled) implements TeachingAssignments
        {
            /**
             * @param  array<string, list<string>>  $taught
             * @param  array<string, list<string>>  $enrolled
             */
            public function __construct(private readonly array $taught, private readonly array $enrolled) {}

            public function subjectIdsTaughtBy(string $userId): array
            {
                return $this->taught[$userId] ?? [];
            }

            public function subjectIdsEnrolledBy(string $userId): array
            {
                return $this->enrolled[$userId] ?? [];
            }
        };
    }
}
