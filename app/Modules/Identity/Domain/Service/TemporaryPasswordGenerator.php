<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Service;

final class TemporaryPasswordGenerator
{
    /** No 0/O/o, 1/l/I: these slips are read off paper by 14-year-olds. */
    public const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';

    public const LENGTH = 8;

    public function generate(): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $password = '';
        for ($i = 0; $i < self::LENGTH; $i++) {
            $password .= self::ALPHABET[random_int(0, $max)];
        }

        return $password;
    }
}
