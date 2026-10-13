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
            ->postJson('/api/v1/classrooms', ['id' => $id, 'name' => 'Química 1', 'subject_ids' => [$subjectId]])
            ->assertStatus(201)
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.name', 'Química 1')
            ->assertJsonPath('data.subject_ids', [$subjectId])
            ->assertJsonPath('data.teachers', [])
            ->assertJsonPath('data.student_count', 0)
            ->assertJsonPath('data.active', true);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/classrooms', ['id' => $id, 'name' => 'Química 1', 'subject_ids' => [$subjectId]])
            ->assertStatus(200)
            ->assertJsonPath('data.id', $id);
    }

    public function test_creating_a_classroom_for_an_unknown_subject_is_rejected(): void
    {
        $token = $this->tokenFor($this->makeUser('admin'));

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/classrooms', ['id' => (string) Str::uuid(), 'name' => 'Química 1', 'subject_ids' => [(string) Str::uuid()]])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'identity.classroom.subject_inactive');
    }

    public function test_an_admin_renames_a_classroom(): void
    {
        $subjectId = $this->makeSubject('Química');
        $token = $this->tokenFor($this->makeUser('admin'));
        $classroomId = $this->makeClassroom($subjectId);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/classrooms/'.$classroomId, ['name' => 'Química A'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Química A')
            ->assertJsonPath('data.subject_ids', [$subjectId]);
    }

    public function test_a_teacher_sees_only_the_classroom_they_are_assigned_to(): void
    {
        $subjectId = $this->makeSubject();
        $admin = $this->tokenFor($this->makeUser('admin'));
        $teacher = $this->makeUser('teacher', 'Ana');
        $otherTeacher = $this->makeUser('teacher', 'Beto');
        $classroomId = $this->makeClassroom($subjectId);

        $this->withHeader('Authorization', 'Bearer '.$admin)
            ->putJson('/api/v1/classrooms/'.$classroomId.'/teachers', ['assignments' => [['teacher_id' => $teacher->getKey(), 'subject_id' => null]]])
            ->assertOk()
            ->assertJsonPath('data.teachers', [['teacher_id' => $teacher->getKey(), 'subject_id' => null]]);

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
            ->putJson('/api/v1/classrooms/'.$classroomId.'/teachers', ['assignments' => [['teacher_id' => $student->getKey(), 'subject_id' => null]]])
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
            ->postJson('/api/v1/classrooms', ['id' => (string) Str::uuid(), 'name' => 'Química 1', 'subject_ids' => [$subjectId]])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'auth.forbidden');
    }

    public function test_an_admin_builds_a_classroom_with_several_subjects_and_mixed_assignments(): void
    {
        $token = $this->tokenFor($this->makeUser('admin'));
        $mat = $this->makeSubject('Matemática');
        $port = $this->makeSubject('Português');
        $ana = $this->makeUser('teacher', 'Ana');
        $carlos = $this->makeUser('teacher', 'Carlos');
        $id = (string) Str::uuid();

        $created = $this->withToken($token)
            ->postJson('/api/v1/classrooms', ['id' => $id, 'name' => '7º A', 'subject_ids' => [$mat, $port]])
            ->assertCreated();
        self::assertEqualsCanonicalizing([$mat, $port], $created->json('data.subject_ids'));

        $this->withToken($token)
            ->putJson("/api/v1/classrooms/$id/teachers", ['assignments' => [
                ['teacher_id' => $ana->getKey(), 'subject_id' => null],
                ['teacher_id' => $carlos->getKey(), 'subject_id' => $mat],
            ]])
            ->assertOk()
            ->assertJsonCount(2, 'data.teachers')
            ->assertJsonFragment(['teacher_id' => $ana->getKey(), 'subject_id' => null])
            ->assertJsonFragment(['teacher_id' => $carlos->getKey(), 'subject_id' => $mat]);
    }

    public function test_assignment_errors_are_reported(): void
    {
        $token = $this->tokenFor($this->makeUser('admin'));
        $mat = $this->makeSubject('Matemática');
        $other = $this->makeSubject('História');
        $ana = $this->makeUser('teacher', 'Ana');
        $id = $this->makeClassroomWith([$mat], name: '7º A');
        $put = fn (array $rows) => $this->withToken($token)->putJson("/api/v1/classrooms/$id/teachers", ['assignments' => $rows]);

        $put([['teacher_id' => $ana->getKey(), 'subject_id' => $other]])
            ->assertStatus(422)->assertJsonPath('error.code', 'identity.classroom.subject_not_assigned');
        $put([['teacher_id' => $ana->getKey(), 'subject_id' => null], ['teacher_id' => $ana->getKey(), 'subject_id' => $mat]])
            ->assertStatus(422)->assertJsonPath('error.code', 'identity.classroom.teacher_assignment_redundant');
        $put([['teacher_id' => $ana->getKey(), 'subject_id' => $mat], ['teacher_id' => $ana->getKey(), 'subject_id' => $mat]])
            ->assertStatus(422)->assertJsonPath('error.code', 'validation.failed');
    }

    public function test_adding_an_inactive_subject_to_a_classroom_is_rejected(): void
    {
        $token = $this->tokenFor($this->makeUser('admin'));
        $id = $this->makeClassroomWith([], name: '7º A');

        $this->withToken($token)
            ->putJson("/api/v1/classrooms/$id/subjects", ['subject_ids' => [(string) Str::uuid()]])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'identity.classroom.subject_inactive');
    }

    public function test_removing_a_subject_drops_its_assignments_and_the_access_they_gave(): void
    {
        $admin = $this->tokenFor($this->makeUser('admin'));
        $mat = $this->makeSubject('Matemática');
        $port = $this->makeSubject('Português');
        $carlos = $this->makeUser('teacher', 'Carlos');
        $bia = $this->makeUser('teacher', 'Bia');
        $id = $this->makeClassroomWith([$mat, $port], [[$carlos, $mat], [$bia, $mat], [$bia, $port]], name: '7º A');

        $this->withToken($admin)
            ->putJson("/api/v1/classrooms/$id/subjects", ['subject_ids' => [$port]])
            ->assertOk()
            ->assertJsonPath('data.subject_ids', [$port])
            ->assertJsonPath('data.teachers', [['teacher_id' => $bia->getKey(), 'subject_id' => $port]]);

        // Carlos had only Matemática there: the classroom is gone from his list.
        $this->app['auth']->forgetGuards();
        $this->withToken($this->tokenFor($carlos))->getJson('/api/v1/classrooms')->assertOk()->assertJsonCount(0, 'data');

        // Bia keeps the classroom, but no longer reads Matemática in it.
        $this->app['auth']->forgetGuards();
        $this->withToken($this->tokenFor($bia))->getJson("/api/v1/classrooms/$id/subjects/$mat/quiz-progress")->assertNotFound();
    }

    public function test_a_student_sees_no_teachers_in_their_classrooms(): void
    {
        $subjectId = $this->makeSubject();
        $student = $this->makeUser('student');
        $this->makeClassroom($subjectId, [$this->makeUser('teacher')], [$student]);

        $this->withToken($this->tokenFor($student))->getJson('/api/v1/classrooms')
            ->assertOk()
            ->assertJsonPath('data.0.teachers', [])
            ->assertJsonPath('data.0.subject_ids', [$subjectId]);
    }
}
