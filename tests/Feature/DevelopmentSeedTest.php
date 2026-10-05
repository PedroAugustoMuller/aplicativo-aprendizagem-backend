<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Identity\Database\Seeders\DevelopmentAccountsSeeder;
use App\Modules\Quiz\Database\Seeders\DevelopmentQuizSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class DevelopmentSeedTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Guards against the dev dataset (a live admin login, ana@escola.br /
     * password) leaking into a production `db:seed --force`, which would also
     * permanently block `identity:create-admin` (it refuses to run once any
     * admin exists).
     */
    public function test_it_does_not_seed_outside_local_or_testing(): void
    {
        $original = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            // --force, not $this->seed(): db:seed's own ConfirmableTrait would
            // otherwise stop to ask for confirmation now that the app reports
            // itself as running in production, which is a different guard than
            // the one this test exercises.
            $this->artisan('db:seed', ['--class' => DevelopmentAccountsSeeder::class, '--force' => true]);
            $this->artisan('db:seed', ['--class' => DevelopmentQuizSeeder::class, '--force' => true]);
        } finally {
            $this->app['env'] = $original;
        }

        self::assertSame(0, DB::table('users')->count());
        self::assertSame(0, DB::table('quiz_attempts')->count());
    }

    public function test_reseeding_twice_leaves_the_dataset_at_the_same_size(): void
    {
        $this->seed();
        $this->seed();

        self::assertSame(4, DB::table('users')->count());
        self::assertSame(2, DB::table('subjects')->count());
        self::assertSame(6, DB::table('topics')->count());
        self::assertSame(14, DB::table('questions')->count());
        self::assertSame(33, DB::table('question_options')->count());
        $periodicTable = DB::table('topics')->where('name', 'Tabela Periódica')->value('id');
        // More than Attempt::MAX_QUESTIONS, so the quiz's random draw is visible in development.
        self::assertSame(11, DB::table('questions')->where('topic_id', $periodicTable)->count());
        self::assertSame(1, DB::table('classrooms')->count());
        self::assertSame(1, DB::table('pending_credentials')->count());
        self::assertSame(3, DB::table('quiz_attempts')->count());
        self::assertSame(30, DB::table('quiz_attempt_questions')->count());
    }

    public function test_carla_has_two_finished_quizzes_in_the_periodic_table(): void
    {
        $this->seed();

        $topicId = DB::table('topics')->where('name', 'Tabela Periódica')->value('id');
        self::assertIsString($topicId);
        self::assertSame(2, DB::table('quiz_attempts')->where('student_id', '0192f0a0-0000-7000-8000-000000000012')->whereNotNull('completed_at')->count());

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor('carla.dias', 'password'))
            ->getJson("/api/v1/topics/$topicId/quiz-history")
            ->assertOk()
            ->assertJsonPath('data.points', 100)
            ->assertJsonPath('data.tier', 'bronze')
            ->assertJsonPath('data.attempts.0.points_change', 40)
            ->assertJsonPath('data.attempts.1.points_change', 60)
            ->assertJsonCount(2, 'data.attempts');
    }

    public function test_the_classroom_question_summary_mixes_carla_and_diego(): void
    {
        $this->seed();

        $topicId = DB::table('topics')->where('name', 'Tabela Periódica')->value('id');
        self::assertIsString($topicId);
        self::assertSame(1, DB::table('quiz_attempts')->where('student_id', '0192f0a0-0000-7000-8000-000000000013')->whereNotNull('completed_at')->count());

        $questions = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor('ana@escola.br', 'password'))
            ->getJson("/api/v1/classrooms/0192f0a0-0000-7000-8000-000000000021/topics/$topicId/question-summary")
            ->assertOk()
            ->assertJsonPath('data.students', 2)
            ->json('data.questions');
        self::assertIsArray($questions);
        $percents = array_column($questions, 'wrong_percent');
        self::assertContains(50, $percents);
        self::assertContains(2, array_column($questions, 'answered'));
    }

    public function test_diego_logs_in_with_the_reissued_temporary_password_and_must_change_it(): void
    {
        $this->seed();
        $this->seed();

        $this->postJson('/api/v1/auth/login', ['login' => 'diego.souza', 'password' => 'Temp2345'])
            ->assertOk()
            ->assertJsonPath('data.must_change_password', true);
    }

    public function test_carla_sees_chemistry_through_her_classroom_but_not_biology(): void
    {
        $this->seed();

        $token = $this->tokenFor('carla.dias', 'password');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/v1/subjects');

        $response->assertOk();

        /** @var list<array{name: string}> $data */
        $data = $response->json('data');
        $names = array_column($data, 'name');

        self::assertContains('Química', $names);
        self::assertNotContains('Biologia', $names);
    }

    public function test_bruno_sees_chemistry_1_in_his_classrooms(): void
    {
        $this->seed();

        $token = $this->tokenFor('bruno@escola.br', 'password');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/v1/classrooms');

        $response->assertOk();

        /** @var list<array{name: string}> $data */
        $data = $response->json('data');
        $names = array_column($data, 'name');

        self::assertContains('Química 1', $names);
    }

    private function tokenFor(string $login, string $password): string
    {
        $token = $this->postJson('/api/v1/auth/login', ['login' => $login, 'password' => $password])->json('data.token');

        if (! is_string($token)) {
            self::fail('Expected the login response to include a string token.');
        }

        return $token;
    }
}
