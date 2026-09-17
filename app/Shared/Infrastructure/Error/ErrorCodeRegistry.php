<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Error;

use App\Shared\Domain\Error\ErrorCode;
use UnitEnum;

final class ErrorCodeRegistry
{
    /** @param list<class-string<ErrorCode&UnitEnum>> $enums */
    public function __construct(private readonly array $enums) {}

    /** @return list<string> */
    public function all(): array
    {
        $codes = [];

        foreach ($this->enums as $enum) {
            foreach ($enum::cases() as $case) {
                $codes[] = $case->code();
            }
        }

        $codes = array_values(array_unique($codes));
        sort($codes);

        return $codes;
    }
}
