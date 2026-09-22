<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Content;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Support\ActsAsUsers;
use Tests\TestCase;

final class ListSubjectsTest extends TestCase
{
    use ActsAsUsers;
    use RefreshDatabase;

    public function test_an_admin_sees_every_subject_including_inactive_ones(): void
    {
        $admin = $this->makeUser('admin');
        $chemistry = $this->makeSubject('Química');
        $biology = $this->makeSubject('Biologia');
        DB::table('subjects')->where('id', $biology)->update(['deactivated_at' => now()]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($admin))
            ->getJson('/api/v1/subjects');

        // EloquentSubjectListReader orders by name: "Biologia" sorts before "Química".
        $response->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $biology)
            ->assertJsonPath('data.0.active', false)
            ->assertJsonPath('data.1.id', $chemistry)
            ->assertJsonPath('data.1.active', true);
    }

    public function test_a_student_sees_only_the_subject_of_their_active_classroom(): void
    {
        $student = $this->makeUser('student');
        $chemistry = $this->makeSubject('Química');
        $biology = $this->makeSubject('Biologia');
        $this->makeClassroom($biology, students: [$student], name: 'Biologia 1');
        // Not enrolled here — must not appear in the response.
        $this->makeClassroom($chemistry, name: 'Química 1');

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($student))
            ->getJson('/api/v1/subjects');

        $response->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $biology);
    }

    public function test_a_student_whose_only_classroom_is_deactivated_sees_nothing(): void
    {
        $student = $this->makeUser('student');
        $chemistry = $this->makeSubject('Química');
        $classroom = $this->makeClassroom($chemistry, students: [$student]);
        DB::table('classrooms')->where('id', $classroom)->update(['deactivated_at' => now()]);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($student))
            ->getJson('/api/v1/subjects')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
