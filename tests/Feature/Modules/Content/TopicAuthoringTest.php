<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Content;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Support\ActsAsUsers;
use Tests\TestCase;

final class TopicAuthoringTest extends TestCase
{
    use ActsAsUsers;
    use RefreshDatabase;

    private string $subjectId;

    private string $teacherToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subjectId = $this->makeSubject('Química');
        $teacher = $this->makeUser('teacher', 'Bruno');
        $this->makeClassroom($this->subjectId, teachers: [$teacher]);
        $this->teacherToken = $this->tokenFor($teacher);
    }

    public function test_the_teacher_of_the_subject_creates_a_topic_after_the_last_one(): void
    {
        $this->makeTopic($this->subjectId, 'Átomos', 4);
        $id = (string) Str::uuid();

        $this->asTeacher()->postJson($this->topicsUrl(), ['id' => $id, 'name' => ' Ligações ', 'description' => 'Iônicas.'])
            ->assertStatus(201)
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.subject_id', $this->subjectId)
            ->assertJsonPath('data.name', 'Ligações')
            ->assertJsonPath('data.description', 'Iônicas.')
            ->assertJsonPath('data.position', 5)
            ->assertJsonPath('data.active', true);
    }

    public function test_an_empty_description_is_stored_as_empty_text(): void
    {
        $this->asTeacher()->postJson($this->topicsUrl(), ['id' => (string) Str::uuid(), 'name' => 'Átomos', 'description' => ''])
            ->assertStatus(201)
            ->assertJsonPath('data.description', '');
    }

    public function test_resending_the_same_create_returns_the_original_and_stores_one_row(): void
    {
        $body = ['id' => (string) Str::uuid(), 'name' => 'Átomos', 'description' => 'x'];

        $this->asTeacher()->postJson($this->topicsUrl(), $body)->assertStatus(201);
        $this->asTeacher()->postJson($this->topicsUrl(), $body)->assertStatus(200)->assertJsonPath('data.position', 0);

        self::assertSame(1, DB::table('topics')->count());
    }

    public function test_the_same_id_with_another_name_is_an_idempotency_conflict(): void
    {
        $id = (string) Str::uuid();
        $this->asTeacher()->postJson($this->topicsUrl(), ['id' => $id, 'name' => 'Átomos', 'description' => ''])->assertStatus(201);

        $this->asTeacher()->postJson($this->topicsUrl(), ['id' => $id, 'name' => 'Íons', 'description' => ''])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'system.idempotency_conflict');
    }

    public function test_a_name_already_used_in_the_subject_is_taken_case_insensitively(): void
    {
        $this->makeTopic($this->subjectId, 'Átomos');

        $this->asTeacher()->postJson($this->topicsUrl(), ['id' => (string) Str::uuid(), 'name' => 'átomos', 'description' => ''])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'content.topic.name_already_taken')
            ->assertJsonPath('error.params.name', 'átomos');
    }

    public function test_a_teacher_of_another_subject_and_a_student_are_forbidden(): void
    {
        $other = $this->makeUser('teacher', 'Outra');
        $this->makeClassroom($this->makeSubject('Biologia'), teachers: [$other], name: 'Biologia 1');
        $body = ['id' => (string) Str::uuid(), 'name' => 'Átomos', 'description' => ''];

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($other))
            ->postJson($this->topicsUrl(), $body)
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'auth.forbidden');

        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($this->makeUser('student')))
            ->postJson($this->topicsUrl(), $body)
            ->assertStatus(403);
    }

    public function test_writing_in_a_deactivated_subject_is_refused(): void
    {
        DB::table('subjects')->where('id', $this->subjectId)->update(['deactivated_at' => now()]);

        $this->asTeacher()->postJson($this->topicsUrl(), ['id' => (string) Str::uuid(), 'name' => 'Átomos', 'description' => ''])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'content.subject.inactive');
    }

    public function test_creating_in_an_unknown_subject_is_not_found(): void
    {
        $this->asTeacher()->postJson('/api/v1/subjects/'.Str::uuid().'/topics', ['id' => (string) Str::uuid(), 'name' => 'Átomos', 'description' => ''])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'content.subject_not_found');
    }

    public function test_an_overlong_name_is_a_validation_error(): void
    {
        $this->asTeacher()->postJson($this->topicsUrl(), ['id' => (string) Str::uuid(), 'name' => str_repeat('a', 121), 'description' => ''])
            ->assertStatus(422)
            ->assertJsonPath('errors.name.0.code', 'validation.max');
    }

    public function test_editing_only_the_description_keeps_the_name(): void
    {
        $id = $this->makeTopic($this->subjectId, 'Átomos');

        $this->asTeacher()->patchJson('/api/v1/topics/'.$id, ['description' => 'Prótons e nêutrons.'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Átomos')
            ->assertJsonPath('data.description', 'Prótons e nêutrons.');
    }

    public function test_renaming_to_a_name_of_another_topic_is_taken(): void
    {
        $this->makeTopic($this->subjectId, 'Átomos');
        $id = $this->makeTopic($this->subjectId, 'Íons', 1);

        $this->asTeacher()->patchJson('/api/v1/topics/'.$id, ['name' => 'ÁTOMOS'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'content.topic.name_already_taken');
    }

    public function test_editing_an_unknown_topic_is_not_found(): void
    {
        $this->asTeacher()->patchJson('/api/v1/topics/'.Str::uuid(), ['name' => 'Átomos'])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'content.topic_not_found');
    }

    public function test_a_topic_is_deactivated_and_reactivated(): void
    {
        $id = $this->makeTopic($this->subjectId);

        $this->asTeacher()->postJson('/api/v1/topics/'.$id.'/deactivate')->assertOk()->assertJsonPath('data.active', false);
        $this->asTeacher()->postJson('/api/v1/topics/'.$id.'/deactivate')->assertOk()->assertJsonPath('data.active', false);
        $this->asTeacher()->postJson('/api/v1/topics/'.$id.'/reactivate')->assertOk()->assertJsonPath('data.active', true);
    }

    private function asTeacher(): self
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->teacherToken);
    }

    private function topicsUrl(): string
    {
        return '/api/v1/subjects/'.$this->subjectId.'/topics';
    }
}
