<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure;

use Illuminate\Support\Facades\Config;

/**
 * Whether "Gerar com IA" is on. Content binds the generator from it and
 * Identity tells the client through /auth/me, so both must agree.
 */
final class AiSuggestions
{
    public const PROVIDERS = ['claude', 'gemini', 'fake'];

    public static function provider(): string
    {
        return Config::string('services.ai.provider', '');
    }

    public static function enabled(): bool
    {
        return in_array(self::provider(), self::PROVIDERS, true);
    }

    /** Staff only: students never author questions. */
    public static function enabledFor(string $role): bool
    {
        return self::enabled() && $role !== 'student';
    }
}
