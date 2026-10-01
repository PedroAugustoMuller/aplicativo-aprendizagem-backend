<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Identity;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\Support\ActsAsUsers;
use Tests\TestCase;

final class ClassroomAdminTest extends TestCase
{
    use ActsAsUsers;
    use RefreshDatabase;

    public function test_an_admin_creates_a_classroom_and_replaying_returns_the_original(): void
    {
        $subjectId = $this->makeSubject();
        $token = $this->tokenFor($this->makeUser('admin'));
        $id = (string) Str::uuid();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/classrooms', ['id' => $id, 'name' => 'Química 1', 'subject_id' => $subjectId])
            ->assertStatus(201)
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.name', 'Química 1')
            ->assertJsonPath('data.subject_id', $subjectId)
            ->assertJsonPath('data.teacher_ids', [])
            ->assertJsonPath('data.student_count', 0)
            ->assertJsonPath('data.active', true);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/classrooms', ['id' => $id, 'name' => 'Química 1', 'subject_id' => $subjectId])
            ->assertStatus(200)
            ->assertJsonPath('data.id', $id);
    }

    public function test_creating_a_classroom_for_an_unknown_subject_is_rejected(): void
    {
        $token = $this->tokenFor($this->makeUser('admin'));

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/classrooms', ['id' => (string) Str::uuid(), 'name' => 'Química 1', 'subject_id' => (string) Str::uuid()])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'identity.classroom.subject_inactive');
    }

    public function test_an_admin_renames_a_classroom_and_changes_its_subject(): void
    {
        $subjectId = $this->makeSubject('Química');
        $otherSubjectId = $this->makeSubject('Biologia');
        $token = $this->tokenFor($this->makeUser('admin'));
        $classroomId = $this->makeClassroom($subjectId);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/classrooms/'.$classroomId, ['name' => 'Biologia 1', 'subject_id' => $otherSubjectId])
            ->assertOk()
            ->assertJsonPath('data.name', 'Biologia 1')
            ->assertJsonPath('data.subject_id', $otherSubjectId);
    }

    public function test_a_teacher_sees_only_the_classroom_they_are_assigned_to(): void
    {
        $subjectId = $this->makeSubject();
        $admin = $this->tokenFor($this->makeUser('admin'));
        $teacher = $this->makeUser('teacher', 'Ana');
        $otherTeacher = $this->makeUser('teacher', 'Beto');
        $classroomId = $this->makeClassroom($subjectId);

        $this->withHeader('Authorization', 'Bearer '.$admin)
            ->putJson('/api/v1/classrooms/'.$classroomId.'/teachers', ['teacher_ids' => [$teacher->getKey()]])
            ->assertOk()
            ->assertJsonPath('data.teacher_ids', [$teacher->getKey()]);

        $this->app['auth']->forgetGuards();

        $asTeacher = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($teacher))
            ->getJson('/api/v1/classrooms');

        $asTeacher->assertOk();
        $data = $asTeacher->json('data');
        self::assertIsArray($data);
        self::assertCount(1, $data);
        $item = $data[0];
        self::assertIsArray($item);
        self::assertSame($classroomId, $item['id']);

        $this->app['auth']->forgetGuards();

        $asOtherTeacher = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($otherTeacher))
            ->getJson('/api/v1/classrooms');

        $asOtherTeacher->assertOk();
        self::assertSame([], $asOtherTeacher->json('data'));
    }

    public function test_an_admin_sees_every_classroom(): void
    {
        $subjectId = $this->makeSubject();
        $admin = $this->makeUser('admin');
        $this->makeClassroom($subjectId, name: 'Química 1');
        $this->makeClassroom($subjectId, name: 'Química 2');

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($admin))
            ->getJson('/api/v1/classrooms');

        $response->assertOk();
        $data = $response->json('data');
        self::assertIsArray($data);
        self::assertCount(2, $data);
    }

    public function test_assigning_a_student_id_as_teacher_is_not_found(): void
    {
        $subjectId = $this->makeSubject();
        $admin = $this->tokenFor($this->makeUser('admin'));
        $student = $this->makeUser('student');
        $classroomId = $this->makeClassroom($subjectId);

        $this->withHeader('Authorization', 'Bearer '.$admin)
            ->putJson('/api/v1/classrooms/'.$classroomId.'/teachers', ['teacher_ids' => [$student->getKey()]])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'identity.teacher_not_found');
    }

    public function test_deactivating_a_classroom_excludes_it_from_the_students_list(): void
    {
        $subjectId = $this->makeSubject();
        $admin = $this->tokenFor($this->makeUser('admin'));
        $student = $this->makeUser('student');
        $classroomId = $this->makeClassroom($subjectId, students: [$student]);

        $this->withHeader('Authorization', 'Bearer '.$admin)
            ->postJson('/api/v1/classrooms/'.$classroomId.'/deactivate')
            ->assertOk()
            ->assertJsonPath('data.active', false);

        $this->app['auth']->forgetGuards();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($student))
            ->getJson('/api/v1/classrooms');

        $response->assertOk();
        self::assertSame([], $response->json('data'));
    }

    public function test_a_teacher_cannot_create_a_classroom(): void
    {
        $subjectId = $this->makeSubject();
        $token = $this->tokenFor($this->makeUser('teacher'));

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/classrooms', ['id' => (string) Str::uuid(), 'name' => 'Química 1', 'subject_id' => $subjectId])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'auth.forbidden');
    }
}
