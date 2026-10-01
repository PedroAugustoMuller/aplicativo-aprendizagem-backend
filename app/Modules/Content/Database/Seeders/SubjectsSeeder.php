<?php

declare(strict_types=1);

namespace App\Modules\Content\Database\Seeders;

use App\Modules\Content\Domain\Entity\Subject;
use App\Modules\Content\Domain\Repository\SubjectRepository;
use App\Modules\Content\Domain\ValueObject\SubjectId;
use App\Modules\Content\Domain\ValueObject\SubjectName;
use Illuminate\Database\Seeder;

/**
 * Fixed ids so other seeders and the frontend e2e suite can reference these
 * subjects without a name lookup. `ChemistryTopicsSeeder` reads CHEMISTRY_ID
 * directly; `DevelopmentAccountsSeeder` (Identity) repeats it as its own
 * constant, since a module may not import another module's internals.
 */
final class SubjectsSeeder extends Seeder
{
    public const CHEMISTRY_ID = '0192f0a0-0000-7000-8000-000000000001';

    public const BIOLOGY_ID = '0192f0a0-0000-7000-8000-000000000002';

    public function run(SubjectRepository $subjects): void
    {
        $subjects->save(Subject::create(new SubjectId(self::CHEMISTRY_ID), new SubjectName('Química')));
        $subjects->save(Subject::create(new SubjectId(self::BIOLOGY_ID), new SubjectName('Biologia')));
    }
}
