<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Repository;

use App\Modules\Identity\Domain\Entity\User;
use App\Modules\Identity\Domain\ValueObject\Email;

interface UserRepository
{
    public function findByEmail(Email $email): ?User;
}
