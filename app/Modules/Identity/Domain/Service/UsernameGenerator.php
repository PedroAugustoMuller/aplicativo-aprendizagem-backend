<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Service;

use App\Modules\Identity\Domain\ValueObject\Username;

/**
 * Derives a login from a student's full name: first name + last name, dot-joined,
 * accents stripped. `iconv` transliteration is locale-dependent across containers,
 * so this uses an explicit map instead of relying on the platform's locale data.
 */
final class UsernameGenerator
{
    /** @var array<string, string> */
    private const TRANSLITERATION = [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c', 'ñ' => 'n',
    ];

    /** @param  callable(string): bool  $isTaken */
    public function generate(string $fullName, callable $isTaken): Username
    {
        $words = array_values(array_filter(array_map(
            fn (string $word): string => $this->slug($word),
            preg_split('/\s+/u', trim($fullName)) ?: [],
        ), fn (string $w): bool => $w !== ''));

        $base = match (count($words)) {
            0 => 'aluno',
            1 => $words[0],
            default => $words[0].'.'.$words[count($words) - 1],
        };

        $base = substr($base, 0, Username::MAX_LENGTH - 3);
        $candidate = $base;
        for ($n = 2; $isTaken($candidate); $n++) {
            $candidate = $base.$n;
        }

        return new Username($candidate);
    }

    private function slug(string $word): string
    {
        $lower = strtr(mb_strtolower($word), self::TRANSLITERATION);

        return (string) preg_replace('/[^a-z0-9]/', '', $lower);
    }
}
