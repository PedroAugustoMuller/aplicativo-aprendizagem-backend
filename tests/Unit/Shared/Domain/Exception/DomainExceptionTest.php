<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\Exception;

use App\Shared\Domain\Exception\BusinessRuleException;
use App\Shared\Domain\Exception\ConflictException;
use App\Shared\Domain\Exception\DomainException;
use App\Shared\Domain\Exception\ForbiddenException;
use App\Shared\Domain\Exception\IdempotencyConflictException;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\Exception\UnauthenticatedException;
use PHPUnit\Framework\TestCase;

final class DomainExceptionTest extends TestCase
{
    public function test_business_rule_exceptions_report_422(): void
    {
        $exception = new class extends BusinessRuleException
        {
            public function errorCode(): string
            {
                return 'content.topic.name_already_taken';
            }

            /**
             * @return array<string, scalar|null>
             */
            public function params(): array
            {
                return ['name' => 'Ligações Químicas'];
            }
        };

        self::assertInstanceOf(DomainException::class, $exception);
        self::assertSame(422, $exception->status());
        self::assertSame('content.topic.name_already_taken', $exception->errorCode());
        self::assertSame(['name' => 'Ligações Químicas'], $exception->params());
    }

    public function test_a_forbidden_exception_is_always_403(): void
    {
        $e = new class extends ForbiddenException
        {
            public function errorCode(): string
            {
                return 'auth.forbidden';
            }

            public function params(): array
            {
                return [];
            }
        };

        self::assertSame(403, $e->status());
        self::assertSame('auth.forbidden', $e->getMessage());
    }

    public function test_the_idempotency_conflict_carries_its_system_code(): void
    {
        $e = new IdempotencyConflictException;

        self::assertSame(409, $e->status());
        self::assertSame('system.idempotency_conflict', $e->errorCode());
        self::assertSame([], $e->params());
    }

    public function test_each_base_fixes_its_own_status(): void
    {
        self::assertSame(401, (new StubUnauthenticated)->status());
        self::assertSame(404, (new StubNotFound)->status());
        self::assertSame(409, (new StubConflict)->status());
        self::assertSame(422, (new StubBusinessRule)->status());
    }

    public function test_the_message_carries_the_code_so_logs_are_greppable(): void
    {
        self::assertStringContainsString('stub.not_found', (new StubNotFound)->getMessage());
    }
}

/*
 * Named fixtures rather than anonymous classes: PHP cannot write
 * `new class extends $someVariable`, so a generic stub helper is impossible here.
 */
final class StubUnauthenticated extends UnauthenticatedException
{
    public function errorCode(): string
    {
        return 'stub.unauthenticated';
    }

    /**
     * @return array<string, scalar|null>
     */
    public function params(): array
    {
        return [];
    }
}

final class StubNotFound extends NotFoundException
{
    public function errorCode(): string
    {
        return 'stub.not_found';
    }

    /**
     * @return array<string, scalar|null>
     */
    public function params(): array
    {
        return [];
    }
}

final class StubConflict extends ConflictException
{
    public function errorCode(): string
    {
        return 'stub.conflict';
    }

    /**
     * @return array<string, scalar|null>
     */
    public function params(): array
    {
        return [];
    }
}

final class StubBusinessRule extends BusinessRuleException
{
    public function errorCode(): string
    {
        return 'stub.business_rule';
    }

    /**
     * @return array<string, scalar|null>
     */
    public function params(): array
    {
        return [];
    }
}
