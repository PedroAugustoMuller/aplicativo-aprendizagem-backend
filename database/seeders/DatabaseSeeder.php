<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Content\Database\Seeders\ChemistryQuestionsSeeder;
use App\Modules\Content\Database\Seeders\ChemistryTopicsSeeder;
use App\Modules\Content\Database\Seeders\SubjectsSeeder;
use App\Modules\Identity\Database\Seeders\DevelopmentAccountsSeeder;
use App\Modules\Quiz\Database\Seeders\DevelopmentQuizSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            SubjectsSeeder::class,
            ChemistryTopicsSeeder::class,
            ChemistryQuestionsSeeder::class,
            DevelopmentAccountsSeeder::class,
            DevelopmentQuizSeeder::class,
        ]);
    }
}
