<?php

declare(strict_types=1);

namespace App\Shared\Domain\Auth;

/**
 * Lives in Shared because both Identity (who holds it) and Content (who checks it)
 * need it, and neither may import the other.
 */
enum Role: string
{
    case Admin = 'admin';
    case Teacher = 'teacher';
    case Student = 'student';

    /** Admins can do everything a teacher can; "staff" is that shared capability. */
    public function isStaff(): bool
    {
        return $this !== self::Student;
    }
}
