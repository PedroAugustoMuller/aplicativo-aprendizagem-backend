<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Quiz\Support;

use App\Shared\Domain\Contract\BankOption;
use App\Shared\Domain\Contract\BankQuestion;
use App\Shared\Domain\Contract\BankTopic;

final class BankFixtures
{
    public const TOPIC = '0192f0a0-0000-7000-8000-00000000a001';

    public const SUBJECT = '0192f0a0-0000-7000-8000-00000000a002';

    public static function topic(bool $available = true): BankTopic
    {
        return new BankTopic(self::TOPIC, self::SUBJECT, $available);
    }

    /** Multiple choice; option `<n>-a` is correct, then `<n>-b`, `<n>-c`. */
    public static function choice(int $n, ?string $explanation = 'Porque sim.'): BankQuestion
    {
        return new BankQuestion(self::id($n, 0), 'multiple_choice', "Pergunta $n?", $explanation, [
            new BankOption(self::id($n, 1), "$n-a", true),
            new BankOption(self::id($n, 2), "$n-b", false),
            new BankOption(self::id($n, 3), "$n-c", false),
        ]);
    }

    public static function trueFalse(int $n): BankQuestion
    {
        return new BankQuestion(self::id($n, 0), 'true_false', "Afirmação $n.", null, [
            new BankOption(self::id($n, 1), 'Verdadeiro', false),
            new BankOption(self::id($n, 2), 'Falso', true),
        ]);
    }

    /** @return list<BankQuestion> */
    public static function choices(int $count): array
    {
        return array_map(static fn (int $n): BankQuestion => self::choice($n), range(1, $count));
    }

    /** Question n's id (part 0) or its option ids (parts 1-3), all valid UUIDs. */
    public static function id(int $n, int $part): string
    {
        return sprintf('0192f0a0-0000-7000-8000-%06d%06d', $n, $part);
    }
}
