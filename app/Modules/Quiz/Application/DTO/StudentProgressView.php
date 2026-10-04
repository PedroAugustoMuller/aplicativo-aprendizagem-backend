<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Application\DTO;

final readonly class StudentProgressView
{
    /** @param list<TopicProgressView> $topics only topics with answers */
    public function __construct(
        public string $id,
        public string $name,
        public string $username,
        public array $topics,
    ) {}
}
