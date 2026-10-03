<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Domain\ValueObject;

/** An option exactly as the student saw it when the attempt started. */
final readonly class SnapshotOption
{
    public function __construct(
        public string $id,
        public string $text,
    ) {}
}
