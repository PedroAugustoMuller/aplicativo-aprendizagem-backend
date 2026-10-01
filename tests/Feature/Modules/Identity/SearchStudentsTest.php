<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Identity;

use App\Modules\Identity\Infrastructure\Persistence\UserModel;
use App\Shared\Infrastructure\Persistence\EloquentAttribute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Support\ActsAsUsers;
use Tests\TestCase;

final class SearchStudentsTest extends TestCase
{
    use ActsAsUsers;
    use RefreshDatabase;

    public function test_a_teacher_finds_students_by_name_case_insensitively(): void
    {
        $teacher = $this->makeUser('teacher');
        $carla = $this->makeUser('student', 'Carla Dias');
        $this->makeUser('student', 'Bruno Lima');

        $this->search($teacher, 'carla')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $carla->getKey())
            ->assertJsonPath('data.0.name', 'Carla Dias')
            ->assertJsonPath('data.0.login', $carla->getAttribute('username'));
    }

    public function test_a_student_is_found_by_username(): void
    {
        $admin = $this->makeUser('admin');
        $student = $this->makeUser('student', 'Diego Souza');
        $username = EloquentAttribute::string($student->getAttribute('username'), 'users.username');

        $this->search($admin, strtoupper($username))
            ->assertOk()
            ->assertJsonPath('data.0.id', $student->getKey());
    }

    public function test_only_active_students_are_returned(): void
    {
        $teacher = $this->makeUser('teacher');
        $this->makeUser('teacher', 'Carla Professora');
        $gone = $this->makeUser('student', 'Carla Antiga');
        $gone->forceFill(['deactivated_at' => now()])->save();
        $carla = $this->makeUser('student', 'Carla Dias');

        $this->search($teacher, 'Carla')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $carla->getKey());
    }

    public function test_each_result_lists_the_students_active_classrooms(): void
    {
        $teacher = $this->makeUser('teacher');
        $carla = $this->makeUser('student', 'Carla Dias');
        $subjectId = $this->makeSubject();
        $quimica = $this->makeClassroom($subjectId, students: [$carla], name: 'Química 1');
        $closed = $this->makeClassroom($subjectId, students: [$carla], name: 'Antiga');
        DB::table('classrooms')->where('id', $closed)->update(['deactivated_at' => now()]);

        $this->search($teacher, 'Carla')
            ->assertOk()
            ->assertJsonPath('data.0.classrooms', [['id' => $quimica, 'name' => 'Química 1']]);
    }

    public function test_wildcards_in_the_search_text_are_literal(): void
    {
        $teacher = $this->makeUser('teacher');
        $this->makeUser('student', 'Ana Lima');

        $this->search($teacher, '%%')->assertOk()->assertJsonCount(0, 'data');
        $this->search($teacher, '__')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_at_most_twenty_results_ordered_by_name(): void
    {
        $teacher = $this->makeUser('teacher');
        foreach (range(25, 1) as $n) {
            $this->makeUser('student', sprintf('Aluno %02d', $n));
        }

        $this->search($teacher, 'Aluno')
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('data.0.name', 'Aluno 01')
            ->assertJsonPath('data.19.name', 'Aluno 20');
    }

    public function test_the_search_text_is_validated(): void
    {
        $teacher = $this->makeUser('teacher');
        $token = $this->tokenFor($teacher);

        foreach (['', '?search=a', '?search='.str_repeat('a', 81)] as $query) {
            $this->withHeader('Authorization', 'Bearer '.$token)
                ->getJson('/api/v1/students'.$query)
                ->assertStatus(422)
                ->assertJsonPath('error.code', 'validation.failed')
                ->assertJsonStructure(['errors' => ['search']]);
        }
    }

    public function test_a_student_may_not_search(): void
    {
        $student = $this->makeUser('student', 'Carla Dias');

        $this->search($student, 'Carla')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'auth.forbidden');
    }

    /** @return TestResponse<JsonResponse> */
    private function search(UserModel $as, string $text): TestResponse
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($as))
            ->getJson('/api/v1/students?search='.urlencode($text));
    }
}
