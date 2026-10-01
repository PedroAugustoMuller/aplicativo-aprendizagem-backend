<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Content;

use App\Modules\Content\Database\Seeders\ChemistryTopicsSeeder;
use App\Modules\Content\Database\Seeders\SubjectsSeeder;
use App\Modules\Content\Infrastructure\Persistence\SubjectModel;
use App\Modules\Content\Infrastructure\Persistence\TopicModel;
use App\Shared\Infrastructure\Persistence\EloquentAttribute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\Support\ActsAsUsers;
use Tests\TestCase;

final class ListTopicsTest extends TestCase
{
    use ActsAsUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // ChemistryTopicsSeeder scopes every topic to SubjectsSeeder::CHEMISTRY_ID
        // and no longer creates the subject itself, so it must run second.
        $this->seed(SubjectsSeeder::class);
        $this->seed(ChemistryTopicsSeeder::class);
    }

    public function test_an_authenticated_user_receives_the_topics_ordered_by_position(): void
    {
        $admin = $this->makeUser('admin');

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($admin))
            ->getJson('/api/v1/subjects/'.$this->chemistrySubjectId().'/topics');

        $response->assertOk()
            ->assertJsonCount(6, 'data')
            ->assertJsonStructure(['data' => [['id', 'name', 'description', 'position']]])
            ->assertJsonPath('data.0.name', 'Matéria e suas Transformações')
            ->assertJsonPath('data.0.position', 1);

        $positions = array_column((array) $response->json('data'), 'position');
        $sorted = $positions;
        sort($sorted);

        self::assertSame($sorted, $positions, 'Topics must arrive ordered by position.');
    }

    public function test_the_endpoint_orders_by_position_even_when_insertion_order_disagrees(): void
    {
        // The seeder's own order matches position order, so it cannot prove the
        // endpoint sorts rather than just returning rows in insertion order.
        // Replace the seeded rows with three inserted out of position order:
        // only a real ORDER BY produces 1, 2, 3 back.
        TopicModel::query()->delete();

        $this->insertTopic(position: 3, name: 'Terceiro');
        $this->insertTopic(position: 1, name: 'Primeiro');
        $this->insertTopic(position: 2, name: 'Segundo');

        $admin = $this->makeUser('admin');

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($admin))
            ->getJson('/api/v1/subjects/'.$this->chemistrySubjectId().'/topics');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.position', 1)
            ->assertJsonPath('data.1.position', 2)
            ->assertJsonPath('data.2.position', 3);
    }

    public function test_a_student_enrolled_in_a_chemistry_classroom_receives_the_topics(): void
    {
        $student = $this->makeUser('student');
        $this->makeClassroom($this->chemistrySubjectId(), students: [$student]);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($student))
            ->getJson('/api/v1/subjects/'.$this->chemistrySubjectId().'/topics')
            ->assertOk()
            ->assertJsonCount(6, 'data');
    }

    public function test_a_student_not_enrolled_is_forbidden(): void
    {
        $student = $this->makeUser('student');

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($student))
            ->getJson('/api/v1/subjects/'.$this->chemistrySubjectId().'/topics')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'auth.forbidden');
    }

    public function test_an_unknown_subject_is_not_found(): void
    {
        $admin = $this->makeUser('admin');

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($admin))
            ->getJson('/api/v1/subjects/'.Str::uuid().'/topics')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'content.subject_not_found');
    }

    public function test_the_old_unscoped_route_is_gone(): void
    {
        $admin = $this->makeUser('admin');

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($admin))
            ->getJson('/api/v1/topics')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'http.not_found');
    }

    public function test_it_rejects_an_anonymous_request_with_the_envelope(): void
    {
        $this->getJson('/api/v1/subjects/'.$this->chemistrySubjectId().'/topics')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'auth.unauthenticated');
    }

    public function test_it_rejects_a_garbage_token(): void
    {
        $this->withHeader('Authorization', 'Bearer not-a-real-token')
            ->getJson('/api/v1/subjects/'.$this->chemistrySubjectId().'/topics')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'auth.unauthenticated');
    }

    public function test_it_rejects_an_anonymous_request_with_no_accept_header(): void
    {
        // getJson() always sends "Accept: application/json", which makes
        // Request::expectsJson() true and skips Authenticate's guest-redirect
        // branch entirely. A plain get() sends no Accept header at all, which is
        // what a bare `curl` or a browser opening the URL directly does — and is
        // the only way to reproduce the guest-redirect path this test pins.
        $this->get('/api/v1/subjects/'.$this->chemistrySubjectId().'/topics')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'auth.unauthenticated');
    }

    private function insertTopic(int $position, string $name): void
    {
        TopicModel::query()->create([
            'id' => (string) Str::uuid(),
            'subject_id' => $this->chemistrySubjectId(),
            'name' => $name,
            'description' => 'Descrição de teste.',
            'position' => $position,
        ]);
    }

    private function chemistrySubjectId(): string
    {
        $subject = SubjectModel::query()->where('name', 'Química')->firstOrFail();

        return EloquentAttribute::string($subject->getKey(), 'subjects.id');
    }
}
