<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Content;

use App\Modules\Content\Database\Seeders\ChemistryTopicsSeeder;
use App\Modules\Content\Infrastructure\Persistence\TopicModel;
use App\Modules\Identity\Database\Seeders\TeacherUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ListTopicsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TeacherUserSeeder::class);
        $this->seed(ChemistryTopicsSeeder::class);
    }

    public function test_an_authenticated_user_receives_the_topics_ordered_by_position(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token())
            ->getJson('/api/v1/topics');

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

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token())
            ->getJson('/api/v1/topics');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.position', 1)
            ->assertJsonPath('data.1.position', 2)
            ->assertJsonPath('data.2.position', 3);
    }

    public function test_it_rejects_an_anonymous_request_with_the_envelope(): void
    {
        $this->getJson('/api/v1/topics')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'auth.unauthenticated');
    }

    public function test_it_rejects_a_garbage_token(): void
    {
        $this->withHeader('Authorization', 'Bearer not-a-real-token')
            ->getJson('/api/v1/topics')
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
        $this->get('/api/v1/topics')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'auth.unauthenticated');
    }

    private function token(): string
    {
        $token = $this->postJson('/api/v1/auth/login', [
            'email' => 'ana@escola.br',
            'password' => 'password',
        ])->json('data.token');

        if (! is_string($token)) {
            self::fail('Expected the login response to include a string token.');
        }

        return $token;
    }

    private function insertTopic(int $position, string $name): void
    {
        TopicModel::query()->create([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'description' => 'Descrição de teste.',
            'position' => $position,
        ]);
    }
}
